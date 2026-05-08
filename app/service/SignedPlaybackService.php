<?php

namespace app\service;

use app\model\PlayUrlCache;
use app\model\User;
use app\model\VideoAsset;
use RuntimeException;
use think\Request;

class SignedPlaybackService
{
    private int $ttl = 1800;

    public function issue(User $user, string $mediaType, int $mediaId, string $source, ?string $mimeType = null): array
    {
        return $this->issueResolved($user, $mediaType, $mediaId, (new MediaSourceResolverService())->resolve($source, $mimeType));
    }

    public function issueAsset(User $user, string $mediaType, int $mediaId, VideoAsset $asset): array
    {
        return $this->issueResolved($user, $mediaType, $mediaId, (new MediaSourceResolverService())->resolveAsset($asset));
    }

    private function issueResolved(User $user, string $mediaType, int $mediaId, array $resolved): array
    {
        $mediaType = strtoupper($mediaType);
        $timestamp = time();
        $nonce = bin2hex(random_bytes(16));
        $ttl = max(60, min($this->ttl, (int) ($resolved['expires_in'] ?? $this->ttl)));
        $expiresAt = date('Y-m-d H:i:s', $timestamp + $ttl);

        PlayUrlCache::create([
            'nonce' => $nonce,
            'user_id' => (int) $user->id,
            'media_type' => $mediaType,
            'media_id' => $mediaId,
            'source_hash' => $resolved['source_hash'],
            'resolved_url' => $resolved['resolved_url'],
            'delivery_type' => $resolved['delivery_type'],
            'mime_type' => $this->playbackMimeType((string) $resolved['mime_type']),
            'expires_at' => $expiresAt,
        ]);

        $params = [
            'type' => strtolower($mediaType),
            'id' => $mediaId,
            'uid' => (int) $user->id,
            'ts' => $timestamp,
            'nonce' => $nonce,
        ];
        $params['sign'] = $this->signature((int) $user->id, $timestamp, $nonce);

        return [
            'play_url' => '/playback/video?' . http_build_query($params),
            'playback_mode' => $resolved['playback_mode'] ?? 'player',
            'mime_type' => $this->playbackMimeType((string) ($resolved['mime_type'] ?? 'video/mp4')),
            'expires_in' => $ttl,
            'expires_at' => $expiresAt,
            'fallback_reason' => $resolved['fallback_reason'] ?? null,
        ];
    }

    public function validate(Request $request): PlayUrlCache
    {
        $type = strtoupper((string) $request->get('type', ''));
        $mediaId = (int) $request->get('id', 0);
        $userId = (int) $request->get('uid', 0);
        $timestamp = (int) $request->get('ts', 0);
        $nonce = (string) $request->get('nonce', '');
        $sign = (string) $request->get('sign', '');

        if (!in_array($type, ['VIDEO', 'LESSON'], true) || $mediaId <= 0 || $userId <= 0 || $timestamp <= 0 || $nonce === '' || $sign === '') {
            throw new RuntimeException('播放签名参数不完整', 403);
        }

        if (time() - $timestamp > $this->ttl || $timestamp - time() > 300) {
            throw new RuntimeException('播放签名已过期', 410);
        }

        if (!hash_equals($this->signature($userId, $timestamp, $nonce), $sign)) {
            throw new RuntimeException('播放签名无效', 403);
        }

        $sessionUserId = (int) (session('web_user_id') ?: 0);

        if ($sessionUserId !== $userId) {
            throw new RuntimeException('播放签名与当前用户不匹配', 403);
        }

        $this->assertRequestOrigin($request);

        $cache = PlayUrlCache::where('nonce', $nonce)->find();

        if (!$cache) {
            throw new RuntimeException('播放缓存不存在', 403);
        }

        if ((int) $cache->user_id !== $userId || (string) $cache->media_type !== $type || (int) $cache->media_id !== $mediaId) {
            throw new RuntimeException('播放缓存与签名不匹配', 403);
        }

        if (strtotime((string) $cache->expires_at) < time()) {
            throw new RuntimeException('播放缓存已过期', 410);
        }

        return $cache;
    }

    private function playbackMimeType(string $mimeType): string
    {
        return strtolower($mimeType) === 'video/quicktime' ? 'video/mp4' : $mimeType;
    }

    private function signature(int $userId, int $timestamp, string $nonce): string
    {
        $secret = $this->secret();

        return hash_hmac('sha256', $userId . $timestamp . $nonce . $secret, $secret);
    }

    private function secret(): string
    {
        $secret = (string) env('PLAYBACK_SIGN_SECRET', '');

        if ($secret !== '' && $secret !== 'change-this-secret-in-env') {
            return $secret;
        }

        $secret = (string) config('jwt.secret', '');

        if ($secret === '' || $secret === 'change-this-secret-in-env') {
            throw new RuntimeException('PLAYBACK_SIGN_SECRET 或 JWT_SECRET 未配置，请先设置安全密钥', 500);
        }

        return $secret;
    }

    private function assertRequestOrigin(Request $request): void
    {
        $host = $this->normalizeHost((string) $request->host());
        $refererHost = $this->hostFromHeader((string) $request->header('referer', ''));
        $originHost = $this->hostFromHeader((string) $request->header('origin', ''));

        foreach ([$refererHost, $originHost] as $headerHost) {
            if ($headerHost !== '' && strcasecmp($headerHost, $host) !== 0) {
                throw new RuntimeException('播放来源不合法', 403);
            }
        }
    }

    private function hostFromHeader(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $host = parse_url($value, PHP_URL_HOST);

        return is_string($host) ? $this->normalizeHost($host) : '';
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));

        if (str_starts_with($host, '[')) {
            $end = strpos($host, ']');

            return $end === false ? $host : substr($host, 1, $end - 1);
        }

        return explode(':', $host)[0];
    }
}
