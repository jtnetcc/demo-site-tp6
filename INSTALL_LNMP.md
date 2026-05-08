# 在线学习平台 LNMP 安装说明

本安装包适用于已经安装好 Nginx + PHP-FPM + MySQL 的服务器，不需要 Docker。

## 服务器要求

- PHP >= 8.1
- MySQL 5.7 / 8.0
- Nginx
- PHP 扩展：pdo、pdo_mysql、mbstring、openssl、json、session
- 推荐扩展：gd、curl、fileinfo
- 如需网盘转存/视频时长识别，建议安装 ffmpeg、ffprobe

## 上传和解压

建议上传到：

```bash
/www/wwwroot/demo-site-tp6
```

解压示例：

```bash
cd /www/wwwroot
tar -xzf demo-site-tp6-installer-*.tar.gz
cd demo-site-tp6
```

## 宝塔浏览器安装

宝塔用户推荐使用网页安装：

1. 网站目录选择项目根目录，例如 `/www/wwwroot/demo-site-tp6`
2. 运行目录选择 `/public`
3. PHP 版本选择 8.1 或 8.2
4. 伪静态选择 ThinkPHP
5. 浏览器访问：

```text
http://服务器IP/install.php
```

按页面提示填写数据库信息和管理员账号，点击开始安装即可。

如果服务器暂时无法设置运行目录为 `/public`，也可以访问 `http://服务器IP/public/install.php` 完成安装；但正式部署仍建议把运行目录设置为 `/public`，避免项目根目录文件暴露。

网页安装器会完成：

- 检查 PHP 环境
- 生成 `.env`
- 创建 runtime 和 uploads 目录
- 初始化数据库表
- 创建管理员账号
- 可选导入演示数据
- 写入安装锁 `runtime/installed.lock`

安装完成后请删除网页安装入口：

```bash
rm -f public/install.php
```

## 命令行安装备用

如果网页安装不可用，可 SSH 执行：

```bash
php install.php
```

只检查环境：

```bash
php install.php --check-only
```

非交互示例：

```bash
php install.php \
  --db-host=127.0.0.1 \
  --db-port=3306 \
  --db-name=tp6_demo \
  --db-user=tp6_user \
  --db-pass='数据库密码' \
  --admin-user=admin \
  --admin-pass='Admin123456'
```

命令行安装器会完成：

- 检查 PHP 环境
- 生成 `.env`
- 创建 runtime 和 uploads 目录
- 初始化数据库表
- 创建管理员账号
- 可选导入演示数据
- 输出 Nginx 配置示例

## 数据库

先创建空数据库：

```sql
CREATE DATABASE tp6_demo DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'tp6_user'@'localhost' IDENTIFIED BY '你的密码';
GRANT ALL PRIVILEGES ON tp6_demo.* TO 'tp6_user'@'localhost';
FLUSH PRIVILEGES;
```

如果使用 `127.0.0.1` 连接，也可以授权：

```sql
CREATE USER 'tp6_user'@'127.0.0.1' IDENTIFIED BY '你的密码';
GRANT ALL PRIVILEGES ON tp6_demo.* TO 'tp6_user'@'127.0.0.1';
FLUSH PRIVILEGES;
```

注意：安装器默认使用 `sql/schema.sql`，不会执行会删表的 `sql/init.sql`。

## Nginx 配置

网站根目录必须指向项目的 `public` 目录。

示例：

```nginx
server {
    listen 80;
    server_name example.com;

    root /www/wwwroot/demo-site-tp6/public;
    index index.php index.html;

    client_max_body_size 2048m;

    location ^~ /uploads/videos/ {
        deny all;
    }

    location ^~ /internal-videos/ {
        internal;
        alias /www/wwwroot/demo-site-tp6/public/uploads/videos/;
    }

    location / {
        try_files $uri $uri/ "/index.php?s=$uri&$query_string";
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

宝塔用户：

- 网站目录：项目根目录
- 运行目录：`/public`
- PHP 版本：8.1 或 8.2
- 伪静态：ThinkPHP
- 上传限制：2048M

## PHP 上传限制

建议调整 PHP 配置：

```ini
upload_max_filesize = 2048M
post_max_size = 2050M
max_file_uploads = 20
max_execution_time = 600
max_input_time = 600
memory_limit = 512M
```

同时 Nginx 设置：

```nginx
client_max_body_size 2048m;
```

## 权限

PHP-FPM 用户必须能写：

```text
项目根目录（安装时生成 .env）
runtime/
public/uploads/
```

常见命令：

```bash
chown www:www .
chown -R www:www runtime public/uploads
chmod 775 .
chmod -R 775 runtime public/uploads
```

Ubuntu/Debian 可能是：

```bash
chown www-data:www-data .
chown -R www-data:www-data runtime public/uploads
chmod 775 .
chmod -R 775 runtime public/uploads
```

## 安装后检查

访问：

```text
/
/login
/admin
/videos
/courses
```

登录安装时创建的管理员账号，检查：

1. 后台是否能进入
2. 站点设置是否能保存
3. 视频管理是否能新增/编辑
4. 封面上传是否正常
5. 视频上传、HTTP 直链或网盘链接是否正常
6. 视频详情页是否能打开
7. 手机页面是否正常

## 安全建议

网页安装完成后必须删除浏览器安装入口：

```bash
rm -f public/install.php
```

根目录 `install.php` 只用于 SSH 命令行安装，浏览器无法执行；如果不再需要命令行安装，也可以删除或限制权限：

```bash
rm -f install.php
# 或
chmod 600 install.php
```

`.env` 不应被 Web 访问；Nginx 示例已禁止访问隐藏文件。

## 常见问题

### 首页 404

确认 Nginx root 指向：

```text
项目目录/public
```

### 路由 404

确认伪静态：

```nginx
location / {
    try_files $uri $uri/ "/index.php?s=$uri&$query_string";
}
```

### 登录提示 JWT_SECRET 未配置

检查 `.env` 是否存在并包含：

```env
JWT_SECRET=...
PLAYBACK_SIGN_SECRET=...
```

### 上传失败

检查 PHP 上传限制、Nginx `client_max_body_size`、`public/uploads` 权限。

### 视频不能播放

确认：

- `public/uploads/videos` 文件存在
- `/uploads/videos/` 禁止直连
- `/internal-videos/` internal alias 配置正确
- `.env` 中 `PLAYBACK_SIGN_SECRET` 已配置
