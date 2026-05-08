<?php

namespace app\service;

class NetdiskShareParserService
{
    private array $providers = [
        'pan.baidu.com' => ['provider' => 'BAIDU', 'name' => 'baidu'],
        'www.aliyundrive.com' => ['provider' => 'OTHER', 'name' => 'aliyundrive'],
        'www.alipan.com' => ['provider' => 'OTHER', 'name' => 'alipan'],
        'cloud.189.cn' => ['provider' => 'OTHER', 'name' => '189cloud'],
        'pan.xunlei.com' => ['provider' => 'OTHER', 'name' => 'xunlei'],
        'www.123pan.com' => ['provider' => 'OTHER', 'name' => '123pan'],
        'drive.uc.cn' => ['provider' => 'OTHER', 'name' => 'ucdrive'],
    ];

    public function parse(string $text): ?array
    {
        $text = trim($text);

        if ($text === '' || !preg_match('#https?://[^\s，。；;]+#iu', $text, $matches)) {
            return null;
        }

        $rawUrl = $this->cleanUrl($matches[0]);
        $provider = $this->provider($rawUrl);

        if (!$provider) {
            return null;
        }

        $shareCode = $this->extractCode($text, $rawUrl);
        $shareUrl = $this->canonicalUrl($rawUrl, $provider['provider'] === 'BAIDU');
        $fileName = $this->extractFileName($text);
        $objectKey = $shareUrl;

        if ($shareCode !== '' && !str_contains($objectKey, 'pwd=')) {
            $objectKey .= (str_contains($objectKey, '?') ? '&' : '?') . 'pwd=' . rawurlencode($shareCode);
        }

        return [
            'source_type' => 'NETDISK',
            'netdisk_provider' => $provider['provider'],
            'share_url' => $shareUrl,
            'share_code' => $shareCode,
            'share_file_name' => $fileName,
            'share_raw_text' => $text,
            'resolver_meta' => ['provider' => $provider['name'], 'host' => strtolower((string) parse_url($rawUrl, PHP_URL_HOST))],
            'object_key' => $objectKey,
            'original_name' => $fileName,
            'mime_type' => $fileName !== '' ? $this->guessMimeType($fileName) : 'video/mp4',
        ];
    }

    public function titleFromFileName(string $fileName): string
    {
        $title = trim(pathinfo($fileName, PATHINFO_FILENAME));

        return $title !== '' ? $title : trim($fileName);
    }

    private function provider(string $url): ?array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        foreach ($this->providers as $domain => $provider) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return $provider;
            }
        }

        if (str_contains($host, 'lanzou')) {
            return ['provider' => 'OTHER', 'name' => 'lanzou'];
        }

        return null;
    }

    private function extractCode(string $text, string $url): string
    {
        $query = parse_url($url, PHP_URL_QUERY);

        if ($query) {
            parse_str($query, $params);

            foreach (['pwd', 'code', 'password'] as $key) {
                if (!empty($params[$key])) {
                    return trim((string) $params[$key]);
                }
            }
        }

        if (preg_match('/(?:提取码|密码|访问码|提取码是|访问密码)\s*[:：]?\s*([A-Za-z0-9]{3,12})/u', $text, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    private function extractFileName(string $text): string
    {
        if (preg_match('/通过网盘分享的文件\s*[:：]\s*([^\r\n]+?)(?:\s+链接\s*[:：]|$)/u', $text, $matches)) {
            return trim($matches[1], " \t\n\r\0\x0B。；;");
        }

        if (preg_match('/([^\r\n\s]+\.(?:mp4|m4v|mov|webm|mkv|avi|flv|m3u8|ogg|ogv))/iu', $text, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    private function canonicalUrl(string $url, bool $stripBaiduPwd): string
    {
        $parts = parse_url($url);

        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }

        $canonical = $parts['scheme'] . '://' . $parts['host'] . ($parts['path'] ?? '');
        $query = [];

        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);

            if ($stripBaiduPwd) {
                unset($query['pwd']);
            }

            $query = array_filter($query, fn ($value) => $value !== '' && $value !== null);
        }

        if ($query) {
            $canonical .= '?' . http_build_query($query);
        }

        return $canonical;
    }

    private function cleanUrl(string $url): string
    {
        return rtrim(trim($url), "。；;,，、\"'）)]}");
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
