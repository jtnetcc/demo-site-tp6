<?php

use Installer\Console;
use Installer\DatabaseInstaller;
use Installer\EnvironmentChecker;
use Installer\EnvWriter;
use Installer\NginxGuide;
use Installer\PermissionInstaller;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo '安装器只能在命令行执行：php install.php';
    exit(1);
}

$root = __DIR__;

require_once $root . '/installer/Console.php';
require_once $root . '/installer/EnvironmentChecker.php';
require_once $root . '/installer/EnvWriter.php';
require_once $root . '/installer/DatabaseInstaller.php';
require_once $root . '/installer/PermissionInstaller.php';
require_once $root . '/installer/NginxGuide.php';

$console = new Console($argv);

try {
    $console->line('========================================');
    $console->line(' 在线学习平台 LNMP 安装器');
    $console->line('========================================');
    $console->line();

    $lockFile = $root . '/runtime/installed.lock';
    $force = $console->has('force');

    if (is_file($lockFile) && !$force) {
        $console->warn('检测到已安装锁文件：runtime/installed.lock');
        $console->warn('如需重新安装，请添加 --force');
        exit(0);
    }

    $checker = new EnvironmentChecker($root, $console);
    $environmentOk = $checker->check();

    if ($console->has('check-only')) {
        exit($environmentOk ? 0 : 1);
    }

    if (!$environmentOk) {
        throw new RuntimeException('环境检查未通过，请先修复上方错误');
    }

    $console->line();
    $console->info('请输入数据库配置');
    $config = [
        'db_host' => $console->option('db-host') ?: $console->ask('数据库主机', '127.0.0.1'),
        'db_port' => $console->option('db-port') ?: $console->ask('数据库端口', '3306'),
        'db_name' => $console->option('db-name') ?: $console->ask('数据库名', 'tp6_demo'),
        'db_user' => $console->option('db-user') ?: $console->ask('数据库用户名', 'tp6_user'),
        'db_pass' => $console->option('db-pass') ?? $console->askPassword('数据库密码'),
    ];

    $console->line();
    $console->info('请输入管理员账号');
    $adminUser = $console->option('admin-user') ?: $console->ask('管理员用户名', 'admin');
    $adminPass = $console->option('admin-pass') ?? $console->askPassword('管理员密码');

    if ($adminPass === '') {
        throw new RuntimeException('管理员密码不能为空');
    }

    if (mb_strlen($adminPass) < 6) {
        throw new RuntimeException('管理员密码不能少于6位');
    }

    $permissions = new PermissionInstaller($root, $console);
    $permissions->ensure();

    $database = new DatabaseInstaller($root, $console);
    $database->connect($config);

    $envPath = (new EnvWriter($root, $console))->write($config, $force);

    if (basename($envPath) !== '.env') {
        $console->warn('当前没有覆盖 .env，应用会继续使用旧配置；请确认后手动替换 .env');
    }

    $database->installSchema($force);
    $database->createAdmin($adminUser, $adminPass);

    if ($console->has('seed-demo') || (!$console->has('no-demo') && $console->confirm('是否导入首页演示数据？', true))) {
        $database->installDemoData();
    }

    if (!is_dir(dirname($lockFile))) {
        mkdir(dirname($lockFile), 0775, true);
    }
    file_put_contents($lockFile, 'installed_at=' . date('c') . PHP_EOL);

    $console->line();
    $console->ok('安装完成');
    $console->line((new NginxGuide($root))->render());
    $console->line('安装后请检查：/、/login、/admin、/videos、/courses');
    $console->line('安全建议：安装完成后删除 install.php，或限制只有 SSH 用户可执行。');
} catch (Throwable $e) {
    $console->error($e->getMessage());
    exit(1);
}
