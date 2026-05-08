<?php

declare(strict_types=1);

use Installer\DatabaseInstaller;
use Installer\EnvironmentChecker;
use Installer\EnvWriter;
use Installer\NginxGuide;
use Installer\PermissionInstaller;
use Installer\WebOutput;

$root = dirname(__DIR__);

require_once $root . '/installer/Console.php';
require_once $root . '/installer/WebOutput.php';
require_once $root . '/installer/EnvironmentChecker.php';
require_once $root . '/installer/EnvWriter.php';
require_once $root . '/installer/DatabaseInstaller.php';
require_once $root . '/installer/PermissionInstaller.php';
require_once $root . '/installer/NginxGuide.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['installer_csrf'])) {
    $_SESSION['installer_csrf'] = bin2hex(random_bytes(32));
}

$lockFile = $root . '/runtime/installed.lock';
$envExists = is_file($root . '/.env');
$installed = is_file($lockFile);
$messages = [];
$errors = [];
$success = false;
$form = [
    'db_host' => envDefault('DB_HOST', '127.0.0.1'),
    'db_port' => envDefault('DB_PORT', '3306'),
    'db_name' => envDefault('DB_DATABASE', 'tp6_demo'),
    'db_user' => envDefault('DB_USERNAME', 'tp6_user'),
    'admin_user' => 'admin',
];

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function posted(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function checked(string $key): bool
{
    return !empty($_POST[$key]);
}

function envDefault(string $key, string $default): string
{
    $value = getenv($key);

    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

function renderMessages(array $messages): string
{
    $html = '';

    foreach ($messages as $item) {
        $type = h($item['type'] ?? 'info');
        $message = h($item['message'] ?? '');
        $html .= '<div class="notice ' . $type . '">' . $message . '</div>';
    }

    return $html;
}

function renderPage(array $data): void
{
    $form = $data['form'];
    $messages = $data['messages'];
    $errors = $data['errors'];
    $success = $data['success'];
    $installed = $data['installed'];
    $envExists = $data['envExists'];
    $csrf = $data['csrf'];
    $nginxGuide = $data['nginxGuide'] ?? '';
    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $wrongWebRoot = str_contains($scriptName, '/public/');

    echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>在线学习平台安装器</title><style>
    :root{--primary:#2563eb;--ok:#16a34a;--warn:#d97706;--err:#dc2626;--line:#e2e8f0;--muted:#64748b;--bg:#f6f8fc;--text:#172033}
    *{box-sizing:border-box}body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;background:radial-gradient(circle at top left,#dbeafe,#f8fafc 42%,#eef2ff);color:var(--text);line-height:1.6}.wrap{max-width:980px;margin:0 auto;padding:34px 18px}.hero{background:linear-gradient(135deg,#1e3a8a,#2563eb 58%,#7c3aed);color:#fff;border-radius:26px;padding:28px;box-shadow:0 20px 60px rgba(37,99,235,.24)}.hero h1{margin:0 0 8px;font-size:34px}.hero p{margin:0;color:rgba(255,255,255,.86)}.card{background:#fff;border:1px solid var(--line);border-radius:20px;padding:22px;margin-top:18px;box-shadow:0 10px 30px rgba(15,23,42,.06)}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.field label{display:block;font-weight:800;margin:0 0 6px}.field input{width:100%;border:1px solid #cbd5e1;border-radius:12px;padding:11px 12px;font:inherit}.check{display:flex;gap:9px;align-items:flex-start;margin:12px 0}.button{border:0;border-radius:999px;background:var(--primary);color:#fff;padding:12px 20px;font-weight:900;font:inherit;cursor:pointer}.button:disabled{opacity:.55}.notice{border-radius:12px;padding:10px 12px;margin:8px 0;background:#f1f5f9;color:#334155}.notice.ok{background:#dcfce7;color:#166534}.notice.warn{background:#fef3c7;color:#92400e}.notice.error{background:#fee2e2;color:#991b1b}.notice.info{background:#eff6ff;color:#1d4ed8}.muted{color:var(--muted)}.danger{color:#991b1b;font-weight:900}.success{background:#dcfce7;border-color:#bbf7d0}.guide{white-space:pre-wrap;overflow:auto;background:#0f172a;color:#e2e8f0;border-radius:16px;padding:16px;font-size:13px}.actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:18px}@media(max-width:720px){.grid{grid-template-columns:1fr}.hero h1{font-size:28px}.wrap{padding:20px 14px}}
    </style></head><body><main class="wrap"><section class="hero"><h1>在线学习平台安装器</h1><p>填写数据库和管理员信息，自动生成 .env、初始化数据库并创建后台账号。</p></section>';

    if ($wrongWebRoot) {
        echo '<section class="card"><h2>运行目录配置错误</h2><p class="danger">当前通过 /public/install.php 访问，说明站点运行目录还没有设置为 /public。安装完成后访问首页出现 403 Forbidden，通常就是这个原因。</p><p>请在宝塔网站设置中将网站目录选择项目根目录，运行目录选择 /public，然后访问 <strong>http://服务器IP/install.php</strong>。</p></section>';
    }

    if ($success) {
        echo '<section class="card success"><h2>安装完成</h2><p>管理员账号：<strong>' . h($form['admin_user'] ?? 'admin') . '</strong></p><p>请访问 <a href="/">前台首页</a>、<a href="/login">登录页</a>，登录后进入 <a href="/admin">后台管理</a>。</p><p class="danger">安全建议：安装成功后请删除服务器上的 public/install.php，避免安装器暴露。</p></section>';
        echo '<section class="card"><h2>安装日志</h2>' . renderMessages($messages) . '</section>';
        if ($nginxGuide !== '') {
            echo '<section class="card"><h2>Nginx / 宝塔提示</h2><div class="guide">' . h($nginxGuide) . '</div></section>';
        }
        echo '</main></body></html>';
        return;
    }

    if ($installed) {
        echo '<section class="card"><h2>已安装</h2><p class="danger">检测到 runtime/installed.lock，网页安装器已停止，不能重复安装。</p><p>如确需重装，请先备份数据，并通过 SSH 手动处理安装锁和数据库。</p></section></main></body></html>';
        return;
    }

    if ($errors) {
        echo '<section class="card"><h2>需要处理的问题</h2>';
        foreach ($errors as $error) {
            echo '<div class="notice error">' . h($error) . '</div>';
        }
        echo '</section>';
    }

    echo '<section class="card"><h2>环境检查</h2>' . renderMessages($messages) . '</section>';

    $installAction = (string) ($_SERVER['SCRIPT_NAME'] ?? '/install.php');

    echo '<form class="card" method="post" action="' . h($installAction) . '"><input type="hidden" name="_csrf" value="' . h($csrf) . '"><h2>数据库配置</h2><div class="grid">
        <div class="field"><label>数据库主机</label><input name="db_host" value="' . h($form['db_host'] ?? '') . '" required></div>
        <div class="field"><label>数据库端口</label><input name="db_port" value="' . h($form['db_port'] ?? '3306') . '" required></div>
        <div class="field"><label>数据库名</label><input name="db_name" value="' . h($form['db_name'] ?? '') . '" required></div>
        <div class="field"><label>数据库用户名</label><input name="db_user" value="' . h($form['db_user'] ?? '') . '" required></div>
        <div class="field"><label>数据库密码</label><input name="db_pass" type="password" autocomplete="new-password"></div>
    </div><h2>管理员账号</h2><div class="grid">
        <div class="field"><label>管理员用户名</label><input name="admin_user" value="' . h($form['admin_user'] ?? 'admin') . '" required></div>
        <div class="field"><label>管理员密码</label><input name="admin_pass" type="password" autocomplete="new-password" required></div>
        <div class="field"><label>确认管理员密码</label><input name="admin_pass_confirm" type="password" autocomplete="new-password" required></div>
    </div><h2>安装选项</h2>';

    if ($envExists) {
        echo '<label class="check"><input type="checkbox" name="overwrite_env" value="1" ' . (checked('overwrite_env') ? 'checked' : '') . '> <span><strong>.env 已存在，确认覆盖</strong><br><span class="muted">不勾选时不会覆盖现有配置。</span></span></label>';
    }

    echo '<label class="check"><input type="checkbox" name="continue_existing_tables" value="1" ' . (checked('continue_existing_tables') ? 'checked' : '') . '> <span>如果数据库已有项目表，继续执行非破坏性建表和默认数据写入</span></label>
        <label class="check"><input type="checkbox" name="seed_demo" value="1" ' . ((checked('seed_demo') || $_SERVER['REQUEST_METHOD'] !== 'POST') ? 'checked' : '') . '> <span>导入首页演示数据</span></label>
        <div class="actions"><button class="button" type="submit">开始安装</button><span class="muted">安装完成后会生成 runtime/installed.lock。</span></div>
    </form><section class="card"><h2>宝塔提示</h2><p>网站目录选择项目根目录，运行目录选择 <strong>/public</strong>，然后访问当前页面进行安装。</p><p class="danger">安装完成后请删除 public/install.php。</p></section></main></body></html>';
}

$environmentOutput = new WebOutput();
$environmentOk = (new EnvironmentChecker($root, $environmentOutput))->check();
$messages = $environmentOutput->messages();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($installed) {
        $errors[] = '检测到已安装锁文件，网页安装器已停止';
    }

    $form = [
        'db_host' => posted('db_host', envDefault('DB_HOST', '127.0.0.1')),
        'db_port' => posted('db_port', envDefault('DB_PORT', '3306')),
        'db_name' => posted('db_name', envDefault('DB_DATABASE', 'tp6_demo')),
        'db_user' => posted('db_user', envDefault('DB_USERNAME', 'tp6_user')),
        'admin_user' => posted('admin_user', 'admin'),
    ];
    $dbPass = (string) ($_POST['db_pass'] ?? '');
    $adminPass = (string) ($_POST['admin_pass'] ?? '');
    $adminPassConfirm = (string) ($_POST['admin_pass_confirm'] ?? '');

    if (empty($_POST['_csrf']) || !hash_equals((string) $_SESSION['installer_csrf'], (string) $_POST['_csrf'])) {
        $errors[] = '页面已过期，请刷新后重试';
    }

    if (!$environmentOk) {
        $errors[] = '环境检查未通过，请先修复缺失扩展或依赖文件';
    }

    foreach (['db_host' => '数据库主机', 'db_port' => '数据库端口', 'db_name' => '数据库名', 'db_user' => '数据库用户名', 'admin_user' => '管理员用户名'] as $key => $label) {
        if (($form[$key] ?? '') === '') {
            $errors[] = $label . '不能为空';
        }
    }

    if (!ctype_digit($form['db_port']) || (int) $form['db_port'] <= 0) {
        $errors[] = '数据库端口必须是有效数字';
    }

    if ($adminPass === '') {
        $errors[] = '管理员密码不能为空';
    } elseif (mb_strlen($adminPass) < 6) {
        $errors[] = '管理员密码不能少于6位';
    }

    if ($adminPass !== $adminPassConfirm) {
        $errors[] = '两次输入的管理员密码不一致';
    }

    if (!$errors) {
        $output = new WebOutput();

        try {
            $permissions = new PermissionInstaller($root, $output);
            $permissions->ensure();

            $config = [
                'db_host' => $form['db_host'],
                'db_port' => $form['db_port'],
                'db_name' => $form['db_name'],
                'db_user' => $form['db_user'],
                'db_pass' => $dbPass,
            ];

            $database = new DatabaseInstaller($root, $output);
            $database->connect($config);

            $envPath = (new EnvWriter($root, $output))->write($config, false, $envExists ? checked('overwrite_env') : true);

            if (basename($envPath) !== '.env') {
                throw new RuntimeException('环境配置未写入 .env，请处理后重新安装');
            }

            $database->installSchema(false, checked('continue_existing_tables'));
            $database->createAdmin($form['admin_user'], $adminPass);

            if (checked('seed_demo')) {
                $database->installDemoData();
            }

            if (!is_dir(dirname($lockFile)) && !mkdir(dirname($lockFile), 0775, true) && !is_dir(dirname($lockFile))) {
                throw new RuntimeException('无法创建 runtime 目录');
            }

            file_put_contents($lockFile, 'installed_at=' . date('c') . PHP_EOL);
            $output->ok('安装锁已写入：runtime/installed.lock');
            $messages = array_merge($messages, $output->messages());
            $success = true;
        } catch (Throwable $e) {
            $messages = array_merge($messages, $output->messages());
            $errors[] = $e->getMessage();
        }
    }
}

renderPage([
    'form' => $form,
    'messages' => $messages,
    'errors' => $errors,
    'success' => $success,
    'installed' => $installed,
    'envExists' => $envExists,
    'csrf' => $_SESSION['installer_csrf'],
    'nginxGuide' => $success ? (new NginxGuide($root))->render() : '',
]);
