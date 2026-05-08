<?php

namespace app\service;

use RuntimeException;

class AdminUploadService
{
    private array $extensions = [
        'VIDEO' => ['mp4', 'webm', 'mov', 'm4v', 'mkv', 'avi', 'flv', 'ogg', 'ogv'],
        'COVER' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
    ];

    private array $mimes = [
        'VIDEO' => ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-m4v', 'video/x-matroska', 'video/x-msvideo', 'video/x-flv', 'video/ogg', 'application/ogg', 'application/octet-stream'],
        'COVER' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
    ];

    public function store(mixed $file, string $kind): array
    {
        if (!$file) {
            throw new RuntimeException('请选择上传文件', 400);
        }

        $kind = in_array($kind, ['VIDEO', 'COVER'], true) ? $kind : 'VIDEO';
        $originalName = $this->originalName($file);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension === '' && method_exists($file, 'getOriginalExtension')) {
            $extension = strtolower((string) $file->getOriginalExtension());
        }

        if (!in_array($extension, $this->extensions[$kind], true)) {
            throw new RuntimeException($kind === 'VIDEO' ? '视频文件扩展名不支持' : '图片文件扩展名不支持', 400);
        }

        $date = date('Ymd');
        $typeDir = $kind === 'VIDEO' ? 'videos' : 'covers';
        $uploadBase = $this->uploadBasePath();
        $relativeDir = $uploadBase . '/' . $typeDir . '/' . $date;
        $targetDir = dirname(__DIR__, 2) . '/public/' . $relativeDir;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException('上传目录不可写', 500);
        }

        $fileName = date('His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        $moved = $this->move($file, $targetDir, $fileName);

        if (!$moved) {
            throw new RuntimeException('文件保存失败', 500);
        }

        $path = $targetDir . '/' . $fileName;
        $detectedMime = $this->detectedMime($path);
        $this->assertValidContent($kind, $path, $detectedMime);

        if ($kind === 'VIDEO') {
            $path = $this->ensureBrowserCompatibleVideo($path);
            $fileName = basename($path);
            $detectedMime = $this->detectedMime($path);
        }

        $metadata = (new MediaMetadataService())->local($path, $originalName, $detectedMime ?: $this->mimeType($file, $path));

        return [
            'storage_key' => $relativeDir . '/' . $fileName,
            'original_name' => $originalName,
            'mime_type' => $metadata['mime_type'] ?? $this->mimeType($file, $path),
            'size_bytes' => $metadata['size_bytes'] ?? $this->size($file, $path),
            'duration_sec' => $kind === 'VIDEO' ? ($metadata['duration_sec'] ?? null) : null,
        ];
    }

    private function uploadBasePath(): string
    {
        $settings = (new SiteSettingService())->settings();
        $path = trim((string) ($settings['other']['storage']['uploadPath'] ?? 'public/uploads'));
        $path = preg_replace('#^public/#', '', $path) ?: 'uploads';
        $path = trim($path, '/');

        if ($path === '' || str_contains($path, '..') || preg_match('#[^a-zA-Z0-9_./-]#', $path)) {
            return 'uploads';
        }

        return $path;
    }

    private function originalName(mixed $file): string
    {
        foreach (['getOriginalName', 'getOriginalFilename', 'getFilename'] as $method) {
            if (method_exists($file, $method)) {
                $name = trim((string) $file->$method());

                if ($name !== '') {
                    return basename($name);
                }
            }
        }

        return 'upload.bin';
    }

    private function move(mixed $file, string $targetDir, string $fileName): bool
    {
        if (method_exists($file, 'move')) {
            $result = $file->move($targetDir, $fileName);
            return $result !== false;
        }

        if (method_exists($file, 'getPathname')) {
            return move_uploaded_file($file->getPathname(), $targetDir . '/' . $fileName);
        }

        return false;
    }

    private function ensureBrowserCompatibleVideo(string $path): string
    {
        $codec = $this->videoCodec($path);

        if (!in_array($codec, ['hevc', 'h265', 'hvc1', 'hev1'], true)) {
            return $path;
        }

        $ffmpeg = $this->commandPath('ffmpeg');

        if ($ffmpeg === '' || !function_exists('exec') || !function_exists('escapeshellarg')) {
            return $path;
        }

        $target = preg_replace('/\.[^.]+$/', '', $path) . '_h264.mp4';
        $command = 'nice -n 15 ' . escapeshellarg($ffmpeg)
            . ' -y -threads 2 -i ' . escapeshellarg($path)
            . ' -map 0:v:0 -map 0:a? -c:v libx264 -preset ultrafast -crf 24 -pix_fmt yuv420p -threads 2 -c:a aac -movflags +faststart '
            . escapeshellarg($target) . ' 2>&1';
        exec($command, $output, $code);

        if ($code !== 0 || !is_file($target) || filesize($target) <= 0) {
            @unlink($target);
            return $path;
        }

        @unlink($path);

        return $target;
    }

    private function videoCodec(string $path): string
    {
        if (!function_exists('shell_exec') || !function_exists('escapeshellarg')) {
            return '';
        }

        $ffprobe = $this->commandPath('ffprobe');

        if ($ffprobe !== '') {
            $command = escapeshellarg($ffprobe) . ' -v error -select_streams v:0 -show_entries stream=codec_name,codec_tag_string -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($path) . ' 2>/dev/null';
            $output = strtolower(trim((string) shell_exec($command)));

            if (str_contains($output, 'hevc') || str_contains($output, 'hvc1') || str_contains($output, 'hev1')) {
                return 'hevc';
            }

            if (str_contains($output, 'h264') || str_contains($output, 'avc1')) {
                return 'h264';
            }
        }

        $strings = strtolower((string) shell_exec('strings ' . escapeshellarg($path) . ' 2>/dev/null | grep -m1 -E "avc1|hvc1|hev1|hevc"'));

        if (str_contains($strings, 'hvc1') || str_contains($strings, 'hev1') || str_contains($strings, 'hevc')) {
            return 'hevc';
        }

        if (str_contains($strings, 'avc1')) {
            return 'h264';
        }

        return '';
    }

    private function commandPath(string $command): string
    {
        if (!function_exists('shell_exec') || !preg_match('/^[a-z0-9_-]+$/i', $command)) {
            return '';
        }

        return trim((string) shell_exec('command -v ' . $command . ' 2>/dev/null'));
    }

    private function assertValidContent(string $kind, string $path, ?string $mime): void
    {
        $valid = $mime !== null && in_array(strtolower($mime), $this->mimes[$kind], true);

        if ($kind === 'COVER') {
            $valid = $valid && @getimagesize($path) !== false;
        }

        if ($valid) {
            return;
        }

        @unlink($path);

        throw new RuntimeException($kind === 'VIDEO' ? '视频文件内容不合法' : '图片文件内容不合法', 400);
    }

    private function mimeType(mixed $file, string $path): ?string
    {
        $detected = $this->detectedMime($path);

        if ($detected !== null) {
            return $detected;
        }

        foreach (['getOriginalMime', 'getMime', 'getMimeType'] as $method) {
            if (method_exists($file, $method)) {
                $mime = trim((string) $file->$method());

                if ($mime !== '') {
                    return $mime;
                }
            }
        }

        return null;
    }

    private function detectedMime(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        if (class_exists(\finfo::class)) {
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

            if ($mime) {
                return strtolower((string) $mime);
            }
        }

        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($path);

            if ($mime) {
                return strtolower((string) $mime);
            }
        }

        return $this->guessMimeType($path);
    }

    private function guessMimeType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'mp4' => 'video/mp4',
            'm4v' => 'video/x-m4v',
            'mov' => 'video/quicktime',
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

    private function size(mixed $file, string $path): ?int
    {
        if (method_exists($file, 'getSize')) {
            $size = (int) $file->getSize();

            if ($size > 0) {
                return $size;
            }
        }

        if (!is_file($path)) {
            return null;
        }

        $size = filesize($path);

        return $size ?: null;
    }
}
