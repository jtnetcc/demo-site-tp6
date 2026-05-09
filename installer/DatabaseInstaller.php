<?php

namespace Installer;

use PDO;
use RuntimeException;

class DatabaseInstaller
{
    private string $root;
    private Console $console;
    private PDO $pdo;

    public function __construct(string $root, Console $console)
    {
        $this->root = $root;
        $this->console = $console;
    }

    public function connect(array $config): void
    {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_port'], $config['db_name']);
        $this->pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('SET NAMES utf8mb4');
        $this->console->ok('数据库连接成功');
    }

    public function installSchema(bool $force = false, ?bool $continueExistingTables = null): void
    {
        $tables = $this->projectTables();
        $existing = $this->existingTables($tables);

        if ($existing && !$force) {
            $this->console->warn('数据库中已存在项目表：' . implode(', ', $existing));

            if ($continueExistingTables === false) {
                throw new RuntimeException('数据库中已存在项目表，请确认继续使用现有表后再安装');
            }

            if ($continueExistingTables !== true && !$this->console->confirm('继续执行非破坏性建表和默认数据写入？', true)) {
                throw new RuntimeException('用户取消数据库初始化');
            }
        }

        $this->importSql($this->root . '/sql/schema.sql');
        $this->importSql($this->root . '/sql/seed-site-settings.sql');
        $this->console->ok('数据库结构和默认站点设置已初始化');
    }

    public function installDemoData(): void
    {
        $this->importSql($this->root . '/sql/seed-demo.sql');
        $this->console->ok('演示数据已导入');
    }

    public function createAdmin(string $username, string $password, string $displayName = '管理员'): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);

        if ($stmt->fetch()) {
            $this->console->warn('管理员用户名已存在，跳过创建：' . $username);
            return;
        }

        $stmt = $this->pdo->prepare("INSERT INTO users (username, display_name, password_hash, role, level, status, created_at, updated_at) VALUES (?, ?, ?, 'ADMIN', 'SVIP', 'ACTIVE', NOW(), NOW())");
        $stmt->execute([$username, $displayName, password_hash($password, PASSWORD_DEFAULT)]);
        $this->console->ok('管理员账号已创建：' . $username);
    }

    private function importSql(string $path): void
    {
        if (!is_file($path)) {
            throw new RuntimeException('SQL 文件不存在：' . $path);
        }

        $sql = file_get_contents($path);

        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('SQL 文件为空：' . $path);
        }

        foreach ($this->splitSql($sql) as $statement) {
            $statement = trim($statement);

            if ($statement === '') {
                continue;
            }

            $this->pdo->exec($statement);
        }
    }

    private function splitSql(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $quote = null;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($quote === null && $char === '-' && $next === '-') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }

            if ($quote === null && $char === '/' && $next === '*') {
                $i += 2;
                while ($i < $length - 1 && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                    $i++;
                }
                $i++;
                continue;
            }

            if (($char === "'" || $char === '"') && ($i === 0 || $sql[$i - 1] !== '\\')) {
                if ($quote === null) {
                    $quote = $char;
                } elseif ($quote === $char) {
                    $quote = null;
                }
            }

            if ($char === ';' && $quote === null) {
                $statements[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $statements[] = $buffer;
        }

        return $statements;
    }

    private function existingTables(array $tables): array
    {
        if (!$tables) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($tables), '?'));
        $stmt = $this->pdo->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ($placeholders)");
        $stmt->execute($tables);

        return array_map('strval', array_column($stmt->fetchAll(), 'TABLE_NAME'));
    }

    private function projectTables(): array
    {
        return [
            'users', 'account_verifications', 'password_resets', 'courses', 'categories', 'tags', 'lessons', 'videos', 'video_tags', 'video_assets',
            'grants', 'watch_histories', 'favorites', 'video_likes', 'comments', 'import_tasks',
            'play_url_caches', 'site_settings',
        ];
    }
}
