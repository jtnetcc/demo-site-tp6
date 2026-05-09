<?php

namespace app\service;


class MediaMetadataService
{
    public function local(string $path, ?string $originalName = null, ?string $mimeType = null): array
    {
        return array_filter([
            'original_name' => $originalName ?: basename($path),
            'mime_type' => $mimeType ?: (is_file($path) ? $this->localMimeType($path) : null),
            'size_bytes' => is_file($path) ? (filesize($path) ?: null) : null,
            'duration_sec' => $this->duration($path),
        ], fn ($value) => $value !== null && $value !== '');
    }

    public function source(string $source): array
    {
        $source = trim(html_entity_decode($source, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($source === '') {
            return [];
        }

        if (preg_match('#^https?://#i', $source)) {
            return $this->http($source);
        }

        return [
            'original_name' => basename(parse_url($source, PHP_URL_PATH) ?: $source),
            'mime_type' => $this->guessMimeType($source),
        ];
    }

    private function http(string $url): array
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $metadata = [
            'original_name' => basename(rawurldecode($path)) ?: basename($path),
            'mime_type' => $this->guessMimeType($path),
        ];

        if (!$this->isSafeHttpUrl($url)) {
            return array_filter($metadata, fn ($value) => $value !== null && $value !== '');
        }

        $headers = $this->head($url);

        if ($this->isSuccessfulResponse($headers)) {
            foreach ($headers as $header) {
                if (stripos($header, 'Content-Type:') === 0) {
                    $mime = trim(explode(';', substr($header, 13), 2)[0]);

                    if ($this->isMediaMimeType($mime)) {
                        $metadata['mime_type'] = $mime;
                    }
                }

                if (stripos($header, 'Content-Length:') === 0) {
                    $size = (int) trim(substr($header, 15));

                    if ($size > 0) {
                        $metadata['size_bytes'] = $size;
                    }
                }
            }
        }

        return array_filter($metadata, fn ($value) => $value !== null && $value !== '');
    }

    private function head(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'timeout' => 5,
                'ignore_errors' => true,
                'max_redirects' => 0,
                'header' => "User-Agent: Mozilla/5.0\r\nAccept: */*\r\n",
            ],
        ]);
        @file_get_contents($url, false, $context);

        return $http_response_header ?? [];
    }

    private function isSuccessfulResponse(array $headers): bool
    {
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $header, $matches)) {
                $status = (int) $matches[1];

                return $status >= 200 && $status < 400;
            }
        }

        return false;
    }

    private function isMediaMimeType(string $mime): bool
    {
        return preg_match('#^(video|audio|image)/#i', $mime) === 1 || in_array(strtolower($mime), ['application/vnd.apple.mpegurl', 'application/x-mpegurl'], true);
    }

    private function isSafeHttpUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        if (!in_array($port, [80, 443], true)) {
            return false;
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return false;
        }

        $ips = $this->resolveIps($host);

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (!$this->isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    private function resolveIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];
        $ips = [];

        foreach ($records as $record) {
            if (!empty($record['ip'])) {
                $ips[] = $record['ip'];
            }

            if (!empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private function localMimeType(string $path): ?string
    {
        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($path);

            if ($mime) {
                return strtolower((string) $mime);
            }
        }

        return $this->guessMimeType($path);
    }

    private function duration(string $path): ?int
    {
        if (!is_file($path)) {
            return null;
        }

        if (!function_exists('shell_exec') || !function_exists('escapeshellarg')) {
            return null;
        }

        $ffprobe = trim((string) shell_exec('command -v ffprobe 2>/dev/null'));

        if ($ffprobe === '') {
            return null;
        }

        $command = escapeshellarg($ffprobe) . ' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($path) . ' 2>/dev/null';
        $output = trim((string) shell_exec($command));

        if ($output === '' || !is_numeric($output)) {
            return null;
        }

        return max(1, (int) ceil((float) $output));
    }

    private function guessMimeType(string $path): string
    {
        return match (strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION))) {
            'm3u8' => 'application/vnd.apple.mpegurl',
            'mp4', 'm4v', 'mov' => 'video/mp4',
            'webm' => 'video/webm',
            'mkv' => 'video/x-matroska',
            'avi' => 'video/x-msvideo',
            'flv' => 'video/x-flv',
            'ogg', 'ogv' => 'video/ogg',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'application/octet-stream',
        };
    }
}
