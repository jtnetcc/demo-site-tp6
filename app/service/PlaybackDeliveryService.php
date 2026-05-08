<?php

namespace app\service;

use app\model\PlayUrlCache;
use app\response\RemoteStreamResponse;
use app\response\StreamFileResponse;
use RuntimeException;
use think\Request;
use think\Response;

class PlaybackDeliveryService
{
    public function deliver(PlayUrlCache $cache, Request $request): Response
    {
        if ((string) $cache->delivery_type === 'REMOTE') {
            return $this->remote($cache, $request);
        }

        return $this->local($cache, $request);
    }

    private function remote(PlayUrlCache $cache, Request $request): Response
    {
        $url = (string) $cache->resolved_url;

        if (!$this->isSafeRemoteUrl($url)) {
            throw new RuntimeException('远程播放地址不安全', 400);
        }

        $probe = $this->probeRemote($url);
        $size = (int) ($probe['size'] ?? 0);
        $mimeType = (string) ($cache->mime_type ?: ($probe['mime_type'] ?? 'application/octet-stream'));
        $range = (string) $request->header('range', '');

        if ($range !== '' && $size > 0 && preg_match('/bytes=(\d*)-(\d*)/', $range, $matches)) {
            if ($matches[1] === '' && $matches[2] === '') {
                return response('', 416, ['Content-Range' => 'bytes */' . $size]);
            }

            if ($matches[1] === '') {
                $suffixLength = (int) $matches[2];
                $start = max(0, $size - $suffixLength);
                $end = $size - 1;
            } else {
                $start = (int) $matches[1];
                $end = $matches[2] === '' ? $size - 1 : (int) $matches[2];
            }

            if ($start > $end || $start < 0 || $end >= $size) {
                return response('', 416, ['Content-Range' => 'bytes */' . $size]);
            }

            $length = $end - $start + 1;

            return (new RemoteStreamResponse(['url' => $url, 'range' => 'bytes=' . $start . '-' . $end], 206))->header([
                'Content-Type' => $mimeType,
                'Content-Length' => (string) $length,
                'Content-Range' => 'bytes ' . $start . '-' . $end . '/' . $size,
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'private, max-age=0, no-store',
            ]);
        }

        $headers = [
            'Content-Type' => $mimeType,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, max-age=0, no-store',
        ];

        if ($size > 0) {
            $headers['Content-Length'] = (string) $size;
        }

        return (new RemoteStreamResponse(['url' => $url], 200))->header($headers);
    }

    private function probeRemote(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'ignore_errors' => true,
                'header' => "User-Agent: Mozilla/5.0\r\nAccept: */*\r\nRange: bytes=0-0\r\n",
            ],
        ]);
        @file_get_contents($url, false, $context);
        $headers = $http_response_header ?? [];
        $status = 0;
        $mimeType = '';
        $size = 0;

        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $header, $matches)) {
                $status = (int) $matches[1];
            }

            if (stripos($header, 'Content-Type:') === 0) {
                $mimeType = trim(explode(';', substr($header, 13), 2)[0]);
            }

            if (stripos($header, 'Content-Range:') === 0 && preg_match('#/(\d+)\s*$#', $header, $matches)) {
                $size = (int) $matches[1];
            }

            if ($size <= 0 && stripos($header, 'Content-Length:') === 0) {
                $length = (int) trim(substr($header, 15));

                if ($length > 1) {
                    $size = $length;
                }
            }
        }

        if ($status < 200 || $status >= 400) {
            throw new RuntimeException('远程视频暂时无法访问', 502);
        }

        return ['mime_type' => $mimeType, 'size' => $size];
    }

    private function isSafeRemoteUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        if (!in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        if (!in_array($port, [80, 443], true) || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            return false;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolveIps($host);

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    private function resolveIps(string $host): array
    {
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

    private function local(PlayUrlCache $cache, Request $request): Response
    {
        $path = (string) $cache->resolved_url;
        $publicRoot = realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public');
        $realPath = realpath($path);

        if (!$publicRoot || !$realPath || !str_starts_with($realPath, $publicRoot . DIRECTORY_SEPARATOR) || !is_file($realPath)) {
            throw new RuntimeException('播放文件不存在', 404);
        }

        $size = filesize($realPath);
        $mimeType = (string) ($cache->mime_type ?: 'application/octet-stream');
        $range = (string) $request->header('range', '');

        if ($range !== '' && preg_match('/bytes=(\d*)-(\d*)/', $range, $matches)) {
            return $this->rangeResponse($realPath, $size, $mimeType, $matches);
        }

        return $this->streamResponse($realPath, 0, $size, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) $size,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, max-age=0, no-store',
        ]);
    }

    private function rangeResponse(string $path, int $size, string $mimeType, array $matches): Response
    {
        if ($matches[1] === '' && $matches[2] === '') {
            return response('', 416, ['Content-Range' => 'bytes */' . $size]);
        }

        if ($matches[1] === '') {
            $suffixLength = (int) $matches[2];
            $start = max(0, $size - $suffixLength);
            $end = $size - 1;
        } else {
            $start = (int) $matches[1];
            $end = $matches[2] === '' ? $size - 1 : (int) $matches[2];
        }

        if ($start > $end || $start < 0 || $end >= $size) {
            return response('', 416, ['Content-Range' => 'bytes */' . $size]);
        }

        $length = $end - $start + 1;

        return $this->streamResponse($path, $start, $length, 206, [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) $length,
            'Content-Range' => 'bytes ' . $start . '-' . $end . '/' . $size,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, max-age=0, no-store',
        ]);
    }

    private function streamResponse(string $path, int $start, int $length, int $code, array $headers): Response
    {
        return (new StreamFileResponse(['path' => $path, 'start' => $start, 'length' => $length], $code))->header($headers);
    }
}
