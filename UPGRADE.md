# 在线学习平台升级维护说明

本文档用于后续版本升级、备份、回滚和常见问题处理。升级前请务必先备份，避免覆盖 `.env`、上传文件或数据库数据。

## 一、升级前必须了解

安装完成后的服务器目录通常类似：

```text
/www/wwwroot/8.148.206.55/demo-site-tp6
```

其中这些内容是服务器运行数据，升级时不要覆盖或删除：

```text
.env
runtime/
public/uploads/
```

说明：

- `.env`：数据库配置、JWT 密钥、播放签名密钥。
- `runtime/`：缓存、日志、会话、安装锁等运行文件。
- `public/uploads/`：封面、视频、本地上传资源。

安装成功后会生成：

```text
runtime/installed.lock
```

后续升级不要删除这个文件，也不要重复运行安装器。

## 二、推荐升级流程

完整流程：

```text
备份数据 → 上传新版 → 覆盖代码 → 执行数据库升级 SQL → 清缓存 → 修权限 → 重载服务 → 验证功能
```

## 三、升级前备份

进入项目目录：

```bash
cd /www/wwwroot/8.148.206.55/demo-site-tp6
```

### 1. 备份配置和上传文件

```bash
tar -czf ../demo-site-tp6-files-backup-$(date +%Y%m%d-%H%M%S).tar.gz .env public/uploads
```

### 2. 备份数据库

如果数据库名是 `tp6_demo`：

```bash
mysqldump -uroot -p tp6_demo > ../tp6_demo-backup-$(date +%Y%m%d-%H%M%S).sql
```

如果不是 `tp6_demo`，以服务器 `.env` 中的数据库名为准：

```bash
grep DB_DATABASE .env
```

然后替换命令中的数据库名。

## 四、上传新版程序

建议把新版压缩包先上传到临时目录，例如：

```text
/www/wwwroot/update-demo-site-tp6
```

解压：

```bash
cd /www/wwwroot/update-demo-site-tp6
tar -xzf demo-site-tp6-web-installer-新版时间.tar.gz
```

不要直接在正式目录里解压覆盖，避免误删上传文件或配置。

## 五、覆盖代码

使用 `rsync` 覆盖代码，并排除服务器运行数据：

```bash
rsync -av \
  --exclude='.env' \
  --exclude='runtime/' \
  --exclude='public/uploads/' \
  /www/wwwroot/update-demo-site-tp6/demo-site-tp6/ \
  /www/wwwroot/8.148.206.55/demo-site-tp6/
```

覆盖完成后，确认这些文件仍然存在：

```bash
ls -l /www/wwwroot/8.148.206.55/demo-site-tp6/.env
ls -ld /www/wwwroot/8.148.206.55/demo-site-tp6/public/uploads
ls -l /www/wwwroot/8.148.206.55/demo-site-tp6/runtime/installed.lock
```

## 六、安装器处理

升级不是重新安装，所以不要再次运行安装器。

如果新版包里带了网页安装入口，升级后建议删除：

```bash
cd /www/wwwroot/8.148.206.55/demo-site-tp6
rm -f public/install.php
```

根目录命令行安装器如果不需要，也可以限制权限：

```bash
chmod 600 install.php
```

## 七、数据库升级

如果新版本提供了数据库升级文件，例如：

```text
sql/upgrade-20260508.sql
```

执行：

```bash
cd /www/wwwroot/8.148.206.55/demo-site-tp6
mysql -uroot -p tp6_demo < sql/upgrade-20260508.sql
```

注意：数据库升级 SQL 应该是非破坏性的，推荐使用：

```sql
CREATE TABLE IF NOT EXISTS ...;
ALTER TABLE ... ADD COLUMN ...;
INSERT IGNORE INTO ...;
UPDATE ... WHERE ...;
```

不要在升级 SQL 中使用：

```sql
DROP TABLE
TRUNCATE TABLE
DELETE FROM 表名
```

除非已经确认备份并明确知道后果。

## 八、清缓存

覆盖代码和数据库升级后，清理缓存：

```bash
cd /www/wwwroot/8.148.206.55/demo-site-tp6
rm -rf runtime/cache/* runtime/temp/*
```

如果页面异常，也可以清理日志外的运行缓存：

```bash
rm -rf runtime/cache/* runtime/temp/* runtime/session/*
```

## 九、修复权限

宝塔常见 PHP-FPM 用户是 `www`：

```bash
cd /www/wwwroot/8.148.206.55/demo-site-tp6
chown -R www:www runtime public/uploads
chmod -R 775 runtime public/uploads
```

如果升级时需要重新生成 `.env` 或写入项目根目录，安装期间项目根目录也需要可写：

```bash
chown www:www .
chmod 775 .
```

安装完成后通常只需要保持下面目录可写：

```text
runtime/
public/uploads/
```

## 十、重载服务

检查 Nginx 配置：

```bash
nginx -t
```

重载 Nginx：

```bash
systemctl reload nginx
```

重启 PHP-FPM。

宝塔用户可以在面板里操作：

```text
软件商店 → PHP → 重启
网站 → 当前站点 → 重载配置
```

如果服务器 PHP-FPM 服务名明确，也可以使用：

```bash
systemctl restart php-fpm
```

不同系统可能是：

```bash
systemctl restart php81-php-fpm
systemctl restart php8.1-fpm
systemctl restart php-fpm-81
```

以服务器实际服务名为准。

## 十一、升级后验证

访问以下页面：

```text
/
/login
/admin
/videos
/courses
```

重点检查：

1. 首页是否正常打开。
2. 登录页是否正常。
3. 管理员是否能登录后台。
4. 后台站点设置是否能保存。
5. 视频列表是否正常。
6. 视频详情页是否能播放。
7. 本地上传封面是否正常。
8. 本地上传视频是否正常。
9. 课程和课时页面是否正常。
10. 手机端页面是否没有横向滚动或错位。

## 十二、常见问题

### 1. 首页 404

优先检查宝塔运行目录：

```text
网站目录：/www/wwwroot/8.148.206.55/demo-site-tp6
运行目录：/public
```

再检查伪静态是否是 ThinkPHP。

宝塔伪静态可使用：

```nginx
location / {
    if (!-e $request_filename) {
        rewrite ^(.*)$ /index.php?s=$1 last;
        break;
    }
}
```

或者 Nginx 配置中使用：

```nginx
location / {
    try_files $uri $uri/ "/index.php?s=$uri&$query_string";
}
```

### 2. 首页 403

通常是运行目录没有设置为 `/public`。

正确配置：

```text
网站目录：项目根目录
运行目录：/public
```

不要访问：

```text
/public/index.php
/public/install.php
```

正确访问：

```text
/
/login
/admin
```

### 3. 上传提示 413 Request Entity Too Large

Nginx 和 PHP 上传限制太小。

Nginx `server` 中增加：

```nginx
client_max_body_size 2048m;
client_body_timeout 1800s;
send_timeout 1800s;
```

PHP 配置修改：

```ini
upload_max_filesize = 2048M
post_max_size = 2050M
max_file_uploads = 20
max_execution_time = 600
max_input_time = 600
memory_limit = 512M
```

修改后重载 Nginx，重启 PHP-FPM。

### 4. 上传目录 Permission denied

执行：

```bash
cd /www/wwwroot/8.148.206.55/demo-site-tp6
mkdir -p runtime/cache runtime/log runtime/session runtime/temp public/uploads/videos public/uploads/covers
chown -R www:www runtime public/uploads
chmod -R 775 runtime public/uploads
```

### 5. 管理员密码登录不上

登录密码至少 6 位。若安装时设置了少于 6 位的密码，需要重置。

生成新密码哈希：

```bash
php -r "echo password_hash('Admin123456', PASSWORD_DEFAULT), PHP_EOL;"
```

进入 MySQL：

```bash
mysql -uroot -p
```

执行：

```sql
USE tp6_demo;
UPDATE users SET password_hash = '这里粘贴生成的哈希' WHERE username = 'admin';
```

然后登录：

```text
用户名：admin
密码：Admin123456
```

### 6. 数据库连接 Access denied

确认安装页或 `.env` 中数据库配置正确：

```text
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tp6_demo
DB_USERNAME=tp6_user
DB_PASSWORD=你的数据库密码
```

宝塔数据库用户名和你手填的用户名可能不同，以宝塔数据库列表实际显示为准。

### 7. fileinfo 缺失

`fileinfo` 已降级为推荐扩展，不是必装扩展。

没有 `fileinfo` 时：

- 图片封面仍会通过 `getimagesize()` 检查。
- 视频 MIME 类型会按扩展名兜底。

### 8. ffmpeg / ffprobe 缺失

`ffmpeg` / `ffprobe` 不是必装。

缺失时影响：

- 不能自动识别视频时长。
- 不能自动转码。
- 不能自动截取视频封面。

不影响基础页面、登录、上传和播放。

## 十三、回滚方案

如果升级后出现严重问题，可以回滚。

### 1. 回滚文件

假设备份文件是：

```text
/www/wwwroot/demo-site-tp6-files-backup-20260508-120000.tar.gz
```

执行：

```bash
cd /www/wwwroot/8.148.206.55/demo-site-tp6
tar -xzf ../demo-site-tp6-files-backup-20260508-120000.tar.gz
```

### 2. 回滚数据库

先确认当前数据库可以覆盖，然后执行：

```bash
mysql -uroot -p tp6_demo < ../tp6_demo-backup-20260508-120000.sql
```

### 3. 清缓存并重启服务

```bash
rm -rf runtime/cache/* runtime/temp/*
nginx -t
systemctl reload nginx
```

然后重启 PHP-FPM。

## 十四、后续建议

后续版本建议增加正式升级器，例如：

```text
upgrade.php
```

或者后台增加：

```text
系统升级
```

升级器可以自动处理：

- 当前版本检测。
- 数据库备份。
- 增量 SQL 执行。
- 清缓存。
- 权限检查。
- 升级日志。

建议后续增加版本表：

```sql
CREATE TABLE IF NOT EXISTS system_versions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    version VARCHAR(50) NOT NULL,
    upgraded_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

这样以后可以根据版本号执行增量升级，避免重复执行 SQL。
