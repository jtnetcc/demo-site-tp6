<?php

namespace Installer;

class EnvironmentChecker
{
    private string $root;
    private Console $console;

    public function __construct(string $root, Console $console)
    {
        $this->root = $root;
        $this->console = $console;
    }

    public function check(): bool
    {
        $ok = true;

        if (PHP_VERSION_ID < 80100) {
            $this->console->error('PHP 版本需要 >= 8.1，当前为 ' . PHP_VERSION);
            $ok = false;
        } else {
            $this->console->ok('PHP 版本：' . PHP_VERSION);
        }

        foreach ($this->requiredExtensions() as $extension) {
            if (!extension_loaded($extension)) {
                $this->console->error('缺少 PHP 扩展：' . $extension);
                $ok = false;
            } else {
                $this->console->ok('PHP 扩展已启用：' . $extension);
            }
        }

        foreach ($this->recommendedExtensions() as $extension) {
            if (!extension_loaded($extension)) {
                $this->console->warn('建议启用 PHP 扩展：' . $extension);
            } else {
                $this->console->ok('推荐扩展已启用：' . $extension);
            }
        }

        if (!is_file($this->root . '/vendor/autoload.php')) {
            $this->console->error('缺少 vendor/autoload.php，请上传 vendor 目录或执行 composer install --no-dev --optimize-autoloader');
            $ok = false;
        } else {
            $this->console->ok('依赖文件存在：vendor/autoload.php');
        }

        if (!$this->commandExists('ffmpeg')) {
            $this->console->warn('未检测到 ffmpeg，网盘转存/视频处理能力可能不可用');
        } else {
            $this->console->ok('ffmpeg 可用');
        }

        if (!$this->commandExists('ffprobe')) {
            $this->console->warn('未检测到 ffprobe，视频时长自动识别可能不可用');
        } else {
            $this->console->ok('ffprobe 可用');
        }

        return $ok;
    }

    private function requiredExtensions(): array
    {
        return ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'json', 'session'];
    }

    private function recommendedExtensions(): array
    {
        return ['gd', 'curl', 'fileinfo'];
    }

    private function commandExists(string $command): bool
    {
        if (!function_exists('shell_exec') || !function_exists('escapeshellarg')) {
            return false;
        }

        $result = trim((string) \shell_exec('command -v ' . \escapeshellarg($command) . ' 2>/dev/null'));

        return $result !== '';
    }
}
