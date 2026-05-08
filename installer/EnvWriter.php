<?php

namespace Installer;

use RuntimeException;

class EnvWriter
{
    private string $root;
    private Console $console;

    public function __construct(string $root, Console $console)
    {
        $this->root = $root;
        $this->console = $console;
    }

    public function write(array $config, bool $force = false, ?bool $overwriteExisting = null): string
    {
        $path = $this->root . '/.env';
        $target = $path;

        if (is_file($path) && !$force) {
            $this->console->warn('.env 已存在，默认不会覆盖');

            if ($overwriteExisting === false) {
                throw new RuntimeException('.env 已存在，请勾选覆盖现有 .env 后再安装');
            }

            if ($overwriteExisting === true || $this->console->confirm('是否覆盖现有 .env？', false)) {
                $target = $path;
            } else {
                $target = $this->root . '/.env.installer.generated';
                $this->console->warn('将配置写入 .env.installer.generated，请确认后手动改名为 .env');
            }
        }

        $jwtSecret = $config['jwt_secret'] ?? bin2hex(random_bytes(32));
        $playbackSecret = $config['playback_secret'] ?? bin2hex(random_bytes(32));

        $content = implode(PHP_EOL, [
            'APP_DEBUG=false',
            'APP_TRACE=false',
            '',
            'DB_CONNECTION=mysql',
            'DB_HOST=' . $config['db_host'],
            'DB_PORT=' . $config['db_port'],
            'DB_DATABASE=' . $config['db_name'],
            'DB_USERNAME=' . $config['db_user'],
            'DB_PASSWORD=' . $config['db_pass'],
            'DB_PREFIX=',
            '',
            'JWT_SECRET=' . $jwtSecret,
            'JWT_ISSUER=demo-site-tp6',
            'JWT_TTL=86400',
            'PLAYBACK_SIGN_SECRET=' . $playbackSecret,
            '',
        ]);

        if (file_put_contents($target, $content) === false) {
            throw new RuntimeException('无法写入 ' . basename($target));
        }

        $this->console->ok('已写入环境配置：' . basename($target));

        return $target;
    }
}
