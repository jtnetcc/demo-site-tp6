<?php

namespace Installer;

use RuntimeException;

class PermissionInstaller
{
    private string $root;
    private Console $console;

    public function __construct(string $root, Console $console)
    {
        $this->root = $root;
        $this->console = $console;
    }

    public function ensure(): void
    {
        $errors = [];

        if (!is_writable($this->root)) {
            $this->console->warn('项目根目录不可写，无法生成 .env');
            $errors[] = '项目根目录';
        } else {
            $this->console->ok('项目根目录可写');
        }

        foreach ($this->directories() as $directory) {
            $path = $this->root . '/' . $directory;

            if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
                throw new RuntimeException('无法创建目录：' . $directory);
            }

            if (!is_writable($path)) {
                $this->console->warn('目录不可写：' . $directory);
                $errors[] = $directory;
            } else {
                $this->console->ok('目录可写：' . $directory);
            }
        }

        if ($errors !== []) {
            $this->console->line();
            $this->console->info('请按服务器 PHP-FPM 用户修复权限，例如：');
            $this->console->line('chown www:www .');
            $this->console->line('chown -R www:www runtime public/uploads');
            $this->console->line('chmod 775 .');
            $this->console->line('chmod -R 775 runtime public/uploads');
            throw new RuntimeException('权限检查未通过：' . implode('、', $errors));
        }
    }

    private function directories(): array
    {
        return [
            'runtime',
            'runtime/cache',
            'runtime/log',
            'runtime/session',
            'runtime/temp',
            'public/uploads',
            'public/uploads/videos',
            'public/uploads/covers',
        ];
    }
}
