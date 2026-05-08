<?php

namespace app\service;

use app\model\SiteSetting;
use RuntimeException;

class BaiduNetdiskResolverService
{
    private string $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/147 Safari/537.36';
    private array $cookies = [];
    private string $referer = '';

    public function resolve(array $asset, ?string $mimeType = null): array
    {
        $config = $this->config();
        $shareUrl = (string) ($asset['share_url'] ?? $asset['object_key'] ?? '');
        $shareCode = (string) ($asset['share_code'] ?? '');
        $fileName = (string) ($asset['share_file_name'] ?? $asset['original_name'] ?? '');
        $fallbackUrl = $this->fallbackUrl($shareUrl, $shareCode);

        if (empty($config['enabled'])) {
            return $this->external($fallbackUrl, $mimeType, '百度网盘直链解析未启用');
        }

        if (($config['mode'] ?? 'direct-or-external') === 'external-only') {
            return $this->external($fallbackUrl, $mimeType, '百度网盘当前配置为只打开网盘页面');
        }

        try {
            $resolved = $this->resolveBuiltin($shareUrl, $shareCode, $fileName, $config);
        } catch (RuntimeException $e) {
            $endpoint = trim((string) ($config['resolverEndpoint'] ?? ''));

            if ($endpoint !== '') {
                try {
                    $resolved = $this->requestResolver($endpoint, [
                        'share_url' => $shareUrl,
                        'share_code' => $shareCode,
                        'file_name' => $fileName,
                        'access_token' => (string) ($config['accessToken'] ?? ''),
                        'cookie' => (string) ($config['cookie'] ?? ''),
                    ]);
                } catch (RuntimeException $endpointError) {
                    if (!empty($config['externalFallback'])) {
                        return $this->external($fallbackUrl, $mimeType, $endpointError->getMessage());
                    }

                    throw $endpointError;
                }
            } elseif (!empty($config['externalFallback'])) {
                return $this->external($fallbackUrl, $mimeType, $e->getMessage());
            } else {
                throw $e;
            }
        }

        $directUrl = (string) ($resolved['direct_url'] ?? $resolved['resolved_url'] ?? $resolved['play_url'] ?? '');

        if ($directUrl === '' || !preg_match('#^https?://#i', $directUrl)) {
            if (!empty($config['externalFallback'])) {
                return $this->external($fallbackUrl, $mimeType, '百度网盘解析未返回可播放地址');
            }

            throw new RuntimeException('百度网盘解析未返回可播放地址', 400);
        }

        return [
            'delivery_type' => 'REMOTE',
            'resolved_url' => $directUrl,
            'source_hash' => hash('sha256', 'baidu:' . $fallbackUrl),
            'mime_type' => (string) ($resolved['mime_type'] ?? $mimeType ?: $this->guessMimeType($fileName)),
            'playback_mode' => 'player',
            'expires_in' => max(60, min(1800, (int) ($resolved['expires_in'] ?? $config['directUrlTtlSec'] ?? 900))),
        ];
    }

    private function resolveBuiltin(string $shareUrl, string $shareCode, string $fileName, array $config): array
    {
        [$surl, $shortSurl] = $this->surl($shareUrl);
        $this->referer = $this->fallbackUrl('https://pan.baidu.com/s/' . $surl, $shareCode);
        $this->cookies = [];
        $quality = (string) ($config['quality'] ?? 'M3U8_AUTO_720');

        $this->request('https://pan.baidu.com/share/init?surl=' . rawurlencode($shortSurl) . ($shareCode !== '' ? '&pwd=' . rawurlencode($shareCode) : ''));

        if ($shareCode !== '') {
            $verify = $this->jsonRequest('https://pan.baidu.com/share/verify?channel=chunlei&clienttype=0&web=1&app_id=250528&surl=' . rawurlencode($shortSurl), 'POST', ['pwd' => $shareCode]);

            if ((int) ($verify['errno'] ?? -1) !== 0) {
                throw new RuntimeException('百度网盘提取码校验失败', 400);
            }
        }

        $this->request($this->referer);
        $listing = $this->jsonRequest('https://pan.baidu.com/share/list?channel=chunlei&clienttype=0&web=1&app_id=250528&desc=1&showempty=0&page=1&num=100&order=time&shorturl=' . rawurlencode($shortSurl) . '&root=1');

        if ((int) ($listing['errno'] ?? -1) !== 0 || empty($listing['list']) || !is_array($listing['list'])) {
            throw new RuntimeException('百度网盘文件列表获取失败', 400);
        }

        $file = $this->selectVideoFile($listing['list'], $fileName);

        if (!$file) {
            throw new RuntimeException('百度网盘分享中未找到可播放视频文件', 400);
        }

        $tpl = $this->jsonRequest('https://pan.baidu.com/share/tplconfig?surl=' . rawurlencode($surl) . '&fields=sign,timestamp&view_mode=1');
        $sign = (string) ($tpl['data']['sign'] ?? '');
        $timestamp = (string) ($tpl['data']['timestamp'] ?? '');
        $shareId = (string) ($listing['share_id'] ?? '');
        $uk = (string) ($listing['uk'] ?? '');
        $fsId = (string) ($file['fs_id'] ?? '');

        if ($sign === '' || $timestamp === '' || $shareId === '' || $uk === '' || $fsId === '') {
            throw new RuntimeException('百度网盘播放签名参数获取失败', 400);
        }

        $url = 'https://pan.baidu.com/share/streaming?' . http_build_query([
            'channel' => 'chunlei',
            'uk' => $uk,
            'fid' => $fsId,
            'sign' => $sign,
            'timestamp' => $timestamp,
            'shareid' => $shareId,
            'type' => $quality,
            'vip' => 0,
        ]);

        return [
            'resolved_url' => $url,
            'mime_type' => 'application/vnd.apple.mpegurl',
            'expires_in' => max(60, min(900, (int) ($config['directUrlTtlSec'] ?? 600))),
        ];
    }

    private function selectVideoFile(array $files, string $fileName): ?array
    {
        $video = null;

        foreach ($files as $file) {
            if (!empty($file['isdir'])) {
                continue;
            }

            $name = (string) ($file['server_filename'] ?? $file['path'] ?? '');
            $isVideo = (($file['mediaType'] ?? '') === 'video') || preg_match('/\.(mp4|m4v|mov|webm|mkv|avi|flv)$/i', $name);

            if (!$isVideo) {
                continue;
            }

            if ($fileName !== '' && $name === $fileName) {
                return $file;
            }

            $video = $video ?: $file;
        }

        return $video;
    }

    private function requestResolver(string $endpoint, array $payload): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);
        $body = file_get_contents($endpoint, false, $context);

        if ($body === false) {
            throw new RuntimeException('百度网盘解析接口请求失败', 400);
        }

        $json = json_decode($body, true);

        if (!is_array($json)) {
            throw new RuntimeException('百度网盘解析接口返回格式不正确', 400);
        }

        if (isset($json['ok']) && !$json['ok']) {
            throw new RuntimeException((string) ($json['error'] ?? '百度网盘解析失败'), 400);
        }

        return is_array($json['data'] ?? null) ? $json['data'] : $json;
    }

    private function jsonRequest(string $url, string $method = 'GET', array $data = []): array
    {
        $body = $this->request($url, $method, $data);
        $json = json_decode($body, true);

        if (!is_array($json)) {
            throw new RuntimeException('百度网盘接口返回格式不正确', 400);
        }

        return $json;
    }

    private function request(string $url, string $method = 'GET', array $data = []): string
    {
        $body = $method === 'POST' ? http_build_query($data) : null;
        $headers = [
            'User-Agent: ' . $this->userAgent,
            'Accept: */*',
            'Referer: ' . $this->referer,
        ];

        if ($this->cookies) {
            $headers[] = 'Cookie: ' . $this->cookieHeader();
        }

        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8';
            $headers[] = 'X-Requested-With: XMLHttpRequest';
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $body,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);
        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            throw new RuntimeException('百度网盘接口请求失败', 400);
        }

        $this->captureCookies($http_response_header ?? []);

        return $response;
    }

    private function captureCookies(array $headers): void
    {
        foreach ($headers as $header) {
            if (stripos($header, 'Set-Cookie:') !== 0) {
                continue;
            }

            $cookie = trim(substr($header, 11));
            $pair = explode(';', $cookie, 2)[0];
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');

            if ($name !== '') {
                $this->cookies[$name] = $value;
            }
        }
    }

    private function cookieHeader(): string
    {
        $pairs = [];

        foreach ($this->cookies as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }

        return implode('; ', $pairs);
    }

    private function external(string $url, ?string $mimeType, string $reason): array
    {
        return [
            'delivery_type' => 'REMOTE',
            'resolved_url' => $url,
            'source_hash' => hash('sha256', 'baidu:' . $url),
            'mime_type' => $mimeType ?: 'video/mp4',
            'playback_mode' => 'external',
            'fallback_reason' => $reason,
        ];
    }

    private function fallbackUrl(string $shareUrl, string $shareCode): string
    {
        if ($shareUrl === '') {
            throw new RuntimeException('百度网盘分享链接为空', 400);
        }

        if ($shareCode === '' || str_contains($shareUrl, 'pwd=')) {
            return $shareUrl;
        }

        return $shareUrl . (str_contains($shareUrl, '?') ? '&' : '?') . 'pwd=' . rawurlencode($shareCode);
    }

    private function surl(string $shareUrl): array
    {
        $path = (string) parse_url($shareUrl, PHP_URL_PATH);

        if (!preg_match('~/s/([^/?#]+)~', $path, $matches)) {
            throw new RuntimeException('百度网盘分享链接格式不正确', 400);
        }

        $surl = $matches[1];
        $shortSurl = str_starts_with($surl, '1') ? substr($surl, 1) : $surl;

        return [$surl, $shortSurl];
    }

    private function config(): array
    {
        $setting = SiteSetting::find(1);
        $other = $setting && is_array($setting->other) ? $setting->other : [];
        $netdisk = is_array($other['netdisk'] ?? null) ? $other['netdisk'] : [];
        $baidu = is_array($netdisk['baidu'] ?? null) ? $netdisk['baidu'] : [];

        return array_replace([
            'enabled' => false,
            'mode' => 'direct-or-external',
            'resolverEndpoint' => '',
            'accessToken' => '',
            'cookie' => '',
            'directUrlTtlSec' => 600,
            'externalFallback' => true,
            'quality' => 'M3U8_AUTO_720',
        ], $baidu);
    }

    private function guessMimeType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'm3u8' => 'application/vnd.apple.mpegurl',
            'mp4', 'm4v', 'mov' => 'video/mp4',
            'webm' => 'video/webm',
            'ogg', 'ogv' => 'video/ogg',
            default => 'video/mp4',
        };
    }
}
