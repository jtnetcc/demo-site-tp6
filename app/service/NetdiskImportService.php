<?php

namespace app\service;

use app\model\ImportTask;
use RuntimeException;

class NetdiskImportService
{
    private string $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/147 Safari/537.36';

    public function store(ImportTask $task): array
    {
        if (strtoupper((string) $task->kind) !== 'VIDEO') {
            throw new RuntimeException('网盘导入仅支持视频文件', 400);
        }

        $asset = $this->assetPayload($task);
        $resolved = (new BaiduNetdiskResolverService())->resolve($asset, 'application/vnd.apple.mpegurl');

        if (($resolved['playback_mode'] ?? 'player') !== 'player') {
            throw new RuntimeException((string) ($resolved['fallback_reason'] ?? '百度网盘资源暂不能解析为可转存地址'), 400);
        }

        $sourceUrl = (string) ($resolved['resolved_url'] ?? '');

        if ($sourceUrl === '' || !preg_match('#^https?://#i', $sourceUrl)) {
            throw new RuntimeException('百度网盘解析未返回可转存地址', 400);
        }

        $ffmpeg = $this->ffmpegPath();
        $target = $this->targetPath($asset);
        $this->runFfmpeg($ffmpeg, $sourceUrl, $target['absolute_path']);

        return [
            'storage_key' => $target['storage_key'],
            'original_name' => $target['original_name'],
            'mime_type' => 'video/mp4',
            'size_bytes' => is_file($target['absolute_path']) ? filesize($target['absolute_path']) : null,
        ];
    }

    private function assetPayload(ImportTask $task): array
    {
        $parsed = (new NetdiskShareParserService())->parse((string) ($task->source_raw_text ?: $task->source_url));

        if (!$parsed) {
            throw new RuntimeException('网盘分享文本无法识别', 400);
        }

        if ((string) $task->source_code !== '') {
            $parsed['share_code'] = (string) $task->source_code;
        }

        return $parsed;
    }

    private function ffmpegPath(): string
    {
        $path = trim((string) shell_exec('command -v ffmpeg 2>/dev/null'));

        if ($path === '') {
            throw new RuntimeException('服务器未安装 ffmpeg，无法把百度网盘视频转存为 MP4；请重建 PHP 容器或安装 ffmpeg 后重试。', 500);
        }

        return $path;
    }

    private function targetPath(array $asset): array
    {
        $date = date('Ymd');
        $relativeDir = 'uploads/videos/' . $date;
        $targetDir = dirname(__DIR__, 2) . '/public/' . $relativeDir;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException('导入目录不可写', 500);
        }

        $originalName = trim((string) ($asset['share_file_name'] ?? $asset['original_name'] ?? 'netdisk-video.mp4'));
        $baseName = pathinfo($originalName, PATHINFO_FILENAME) ?: 'netdisk-video';
        $fileName = date('His') . '_' . bin2hex(random_bytes(6)) . '_' . $this->safeName($baseName) . '.mp4';

        return [
            'storage_key' => $relativeDir . '/' . $fileName,
            'absolute_path' => $targetDir . '/' . $fileName,
            'original_name' => $baseName . '.mp4',
        ];
    }

    private function safeName(string $name): string
    {
        $name = preg_replace('/[^\pL\pN._-]+/u', '_', $name) ?: 'video';
        $name = trim($name, '._-');

        return mb_substr($name !== '' ? $name : 'video', 0, 80);
    }

    private function runFfmpeg(string $ffmpeg, string $sourceUrl, string $targetPath): void
    {
        set_time_limit(0);

        $headers = "Referer: https://pan.baidu.com/\r\n";
        $command = implode(' ', [
            escapeshellarg($ffmpeg),
            '-hide_banner',
            '-loglevel',
            'error',
            '-y',
            '-user_agent',
            escapeshellarg($this->userAgent),
            '-headers',
            escapeshellarg($headers),
            '-i',
            escapeshellarg($sourceUrl),
            '-c',
            'copy',
            '-movflags',
            '+faststart',
            escapeshellarg($targetPath),
        ]);
        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            throw new RuntimeException('启动视频转存进程失败', 500);
        }

        foreach ($pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }

        $output = '';
        $startedAt = time();
        $timeout = 7200;

        while (true) {
            $status = proc_get_status($process);
            $output .= stream_get_contents($pipes[1]);
            $output .= stream_get_contents($pipes[2]);

            if (!$status['running']) {
                break;
            }

            if (time() - $startedAt > $timeout) {
                proc_terminate($process);
                throw new RuntimeException('视频转存超时，请稍后重试或降低视频清晰度', 500);
            }

            usleep(200000);
        }

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        $exitCode = proc_close($process);

        if ($exitCode !== 0 || !is_file($targetPath) || filesize($targetPath) <= 0) {
            @unlink($targetPath);
            throw new RuntimeException('视频转存失败：' . trim($output), 500);
        }
    }
}
