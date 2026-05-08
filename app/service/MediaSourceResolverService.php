<?php

namespace app\service;

use app\model\VideoAsset;
use RuntimeException;

class MediaSourceResolverService
{
    public function resolve(string $source, ?string $mimeType = null): array
    {
        $source = $this->normalizeSource($source);

        if ($source === '') {
            throw new RuntimeException('播放资源为空', 400);
        }

        $parsed = (new NetdiskShareParserService())->parse($source);

        if ($parsed) {
            if (($parsed['netdisk_provider'] ?? '') === 'BAIDU') {
                return (new BaiduNetdiskResolverService())->resolve($parsed, $mimeType);
            }

            return $this->remote((string) $parsed['object_key'], $mimeType, (string) $parsed['object_key'], 'external', '该网盘暂不支持站内直连播放，请使用外部打开入口');
        }

        if (preg_match('#^https?://#i', $source)) {
            if ($this->isNetdiskShare($source)) {
                return $this->remote($source, $mimeType, $source, 'external', '百度网盘直链解析未配置');
            }

            return $this->remote($source, $mimeType);
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $source)) {
            throw new RuntimeException('该资源协议已不支持，请改用本地上传、本地路径、HTTP 直链或网盘链接', 400);
        }

        return $this->local($source, $mimeType);
    }

    public function resolveAsset(VideoAsset $asset): array
    {
        $sourceType = strtoupper((string) ($asset->source_type ?: ''));

        if ($sourceType === 'NETDISK' || $asset->netdisk_provider || $asset->share_url) {
            $provider = strtoupper((string) ($asset->netdisk_provider ?: 'BAIDU'));

            if ($provider === 'BAIDU') {
                return (new BaiduNetdiskResolverService())->resolve([
                    'object_key' => (string) $asset->object_key,
                    'share_url' => (string) $asset->share_url,
                    'share_code' => (string) $asset->share_code,
                    'share_file_name' => (string) $asset->share_file_name,
                    'original_name' => (string) $asset->original_name,
                ], $asset->mime_type ? (string) $asset->mime_type : null);
            }

            return $this->remote((string) ($asset->share_url ?: $asset->object_key), $asset->mime_type ? (string) $asset->mime_type : null, (string) $asset->object_key, 'external', '该网盘暂不支持站内直连播放，请使用外部打开入口');
        }

        return $this->resolve((string) $asset->object_key, $asset->mime_type ? (string) $asset->mime_type : null);
    }

    private function normalizeSource(string $source): string
    {
        return trim(html_entity_decode($source, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function local(string $source, ?string $mimeType): array
    {
        $relative = ltrim(str_replace('\\', '/', $source), '/');

        if (str_starts_with($relative, 'public/')) {
            $relative = substr($relative, 7);
        }

        if (str_contains($relative, '../') || $relative === '..') {
            throw new RuntimeException('播放资源路径不合法', 400);
        }

        $root = rtrim(dirname(__DIR__, 2), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'public';
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        return [
            'delivery_type' => 'LOCAL',
            'resolved_url' => $path,
            'source_hash' => hash('sha256', 'local:' . $relative),
            'mime_type' => $this->playbackMimeType($mimeType ?: $this->guessMimeType($relative)),
            'playback_mode' => 'player',
        ];
    }

    private function remote(string $url, ?string $mimeType, ?string $source = null, string $playbackMode = 'player', ?string $fallbackReason = null): array
    {
        return [
            'delivery_type' => 'REMOTE',
            'resolved_url' => $url,
            'source_hash' => hash('sha256', 'remote:' . ($source ?: $url)),
            'mime_type' => $this->playbackMimeType($mimeType ?: $this->guessMimeType(parse_url($url, PHP_URL_PATH) ?: '')),
            'playback_mode' => $playbackMode,
            'fallback_reason' => $fallbackReason,
        ];
    }

    private function isNetdiskShare(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        foreach (['pan.baidu.com', 'www.aliyundrive.com', 'www.alipan.com', 'cloud.189.cn', 'pan.xunlei.com', 'www.123pan.com', 'www.lanzou', 'drive.uc.cn'] as $domain) {
            if (str_contains($host, $domain)) {
                return true;
            }
        }

        return false;
    }

    private function playbackMimeType(string $mimeType): string
    {
        return strtolower($mimeType) === 'video/quicktime' ? 'video/mp4' : $mimeType;
    }

    private function guessMimeType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'm3u8' => 'application/vnd.apple.mpegurl',
            'mp4', 'm4v', 'mov' => 'video/mp4',
            'webm' => 'video/webm',
            'ogg', 'ogv' => 'video/ogg',
            default => 'application/octet-stream',
        };
    }
}
