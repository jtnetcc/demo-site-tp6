# 在线学习平台 demo-site-tp6

基于 ThinkPHP 的在线学习 / 视频课程演示项目，支持服务端渲染页面、会员等级、课程授权、安全播放、后台管理、账号注册验证、联系方式绑定和密码找回。

项目定位是一个可直接部署的轻量学习站点模板：前台提供课程、视频、搜索和个人中心；后台提供用户、视频、课程、课时、授权、评论、导入任务和站点设置管理。

## 功能特性

### 前台功能

- 首页、视频列表、课程列表、搜索页。
- 视频详情页：播放、点赞、收藏、评论。
- 课程详情页：课程介绍、课时列表、课时播放。
- 个人中心：学习概览、观看历史、收藏、已授权课程、个人资料。
- 响应式页面，兼容电脑、平板和手机访问。

### 账号体系

- 用户名、邮箱、手机号三种注册方式。
- 邮箱 / 手机号注册需要验证码验证后直接完成注册。
- 用户名注册后会强制绑定邮箱或手机号。
- 邮箱 / 手机号绑定必须通过验证码。
- 支持邮箱重置链接和手机验证码找回密码。
- Web 登录、注册、绑定、找回密码流程包含 CSRF 防护。
- API 登录使用 Bearer Token。

### 权限与会员

- 用户角色：普通用户、管理员。
- 用户等级：普通、VIP、SVIP。
- 视频可按等级限制观看。
- 课程可按等级限制，也可由管理员单独授权。
- 授权可设置过期时间。
- 未满足权限时页面会显示引导信息，不直接暴露播放地址。

### 视频与播放

- 支持本地上传视频和封面。
- 支持 HTTP 直链资源。
- 支持网盘分享链接记录和导入任务。
- 播放地址按用户权限生成，降低资源直链泄露风险。
- 本地视频建议通过 Nginx internal 路由保护。
- `ffmpeg` / `ffprobe` 不是必装；安装后可用于视频时长识别等扩展能力。

### 后台管理

后台入口：`/admin`

- 仪表盘数据概览。
- 用户管理：创建、编辑、禁用、删除用户。
- 视频管理：视频资料、封面、播放资源、会员等级。
- 分类和标签管理。
- 课程和课时管理。
- 课程授权管理。
- 评论审核：隐藏、恢复、删除。
- 观看历史管理。
- 导入任务管理。
- 站点设置：基础信息、导航页头、页脚、SEO、首页、存储、验证码 / 找回密码、维护模式等。

### 安装与维护

- 支持网页安装器。
- 支持命令行安装器。
- 支持 Docker Compose 本地运行。
- 提供 LNMP / 宝塔部署说明。
- 提供升级、备份、回滚和常见问题文档。

## 技术栈

- PHP >= 8.1
- ThinkPHP / topthink framework
- MySQL 5.7 / 8.0
- Nginx
- Composer
- Docker Compose（可选）

Composer 依赖见 [composer.json](composer.json)。

## 目录结构

```text
app/                 应用代码
  controller/        控制器
  middleware/        Web / API / 后台中间件
  model/             数据模型
  service/           业务服务
  validate/          表单校验
  view/              服务端渲染模板
config/              ThinkPHP 配置
route/               Web 和 API 路由
public/              Web 入口目录
public/uploads/      上传文件目录，运行数据不提交
installer/           安装器逻辑
sql/                 初始化 SQL 和演示数据
nginx/               Docker Nginx 配置
php/                 Docker PHP 配置
scripts/             打包脚本
dist/                本地安装包输出目录，不提交
runtime/             缓存、日志、会话、安装锁，不提交
```

## 环境要求

### 必需

- PHP >= 8.1
- MySQL 5.7 或 8.0
- Nginx 或其他支持 PHP-FPM 的 Web Server
- PHP 扩展：`pdo`、`pdo_mysql`、`mbstring`、`openssl`、`json`、`session`

### 推荐

- `gd`：图片处理能力。
- `curl`：请求外部接口。
- `fileinfo`：更准确识别上传文件 MIME；不是必装。
- `ffmpeg` / `ffprobe`：识别视频时长、后续转码或截图能力；不是必装。

## 快速开始：Docker 本地运行

适合本地开发和演示。

```bash
docker compose up -d
```

默认服务：

- Web：`http://127.0.0.1:8080`
- MySQL 容器端口：`3306`
- MySQL 主机映射端口：`3307`
- MySQL 数据库：`tp6_demo`
- MySQL 用户：`tp6_user`
- MySQL 密码：`tp6_pass`
- MySQL root 密码：`root123`

查看服务：

```bash
docker compose ps
```

停止服务：

```bash
docker compose down
```

如需清空本地 Docker 数据库卷：

```bash
docker compose down -v
```

## 生产部署：LNMP / 宝塔

详细说明见 [INSTALL_LNMP.md](INSTALL_LNMP.md)。

关键点：

1. 网站目录选择项目根目录。
2. 运行目录必须设置为 `/public`。
3. PHP 版本选择 8.1 或 8.2。
4. 伪静态选择 ThinkPHP。
5. `runtime/` 和 `public/uploads/` 必须可写。
6. 安装后删除网页安装入口 `public/install.php`。

### 宝塔网页安装

上传并解压安装包到例如：

```text
/www/wwwroot/demo-site-tp6
```

宝塔站点设置：

```text
网站目录：/www/wwwroot/demo-site-tp6
运行目录：/public
伪静态：ThinkPHP
PHP：8.1 或 8.2
```

然后访问：

```text
http://服务器IP/install.php
```

如果暂时无法设置运行目录，也可以访问：

```text
http://服务器IP/public/install.php
```

但正式运行仍建议把站点运行目录设置为 `/public`，避免根目录文件暴露。

### 命令行安装

```bash
php install.php
```

只检查环境：

```bash
php install.php --check-only
```

非交互安装示例：

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

## 数据库初始化

新安装站点使用：

```text
sql/schema.sql
```

演示数据使用：

```text
sql/seed-demo.sql
```

站点设置默认数据：

```text
sql/seed-site-settings.sql
```

注意：`sql/init.sql` 是完整重建用途，会先删除已有表，不要在生产升级中直接执行。

## Nginx 配置要点

网站 root 必须指向项目的 `public` 目录。

核心配置示例：

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

上传大视频时建议同时调整 PHP：

```ini
upload_max_filesize = 2048M
post_max_size = 2050M
max_file_uploads = 20
max_execution_time = 600
max_input_time = 600
memory_limit = 512M
```

## 环境变量

安装器会生成 `.env`。示例见 [.env.example](.env.example)。

关键配置：

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tp6_demo
DB_USERNAME=tp6_user
DB_PASSWORD=你的数据库密码

APP_DEBUG=false
APP_TRACE=false

JWT_SECRET=请使用随机安全密钥
PLAYBACK_SIGN_SECRET=请使用随机安全密钥
```

说明：

- `.env` 不应提交到 Git。
- `JWT_SECRET` 用于 API 登录 Token。
- `PLAYBACK_SIGN_SECRET` 用于安全播放签名。
- 生产环境必须关闭 `APP_DEBUG`。

## 账号注册、绑定和找回密码

后台路径：

```text
后台管理 → 站点设置 → 验证码/找回密码
```

可配置：

- 总开关。
- 邮箱验证码开关和 SMTP 信息。
- 手机验证码开关和短信 HTTP 接口。
- 验证码长度、有效期、最大错误次数、重发冷却时间。

流程说明：

- 用户名注册：创建账号后必须绑定邮箱或手机号。
- 邮箱注册：先验证邮箱验证码，注册后邮箱视为已验证。
- 手机注册：先验证短信验证码，注册后手机号视为已验证。
- 更换联系方式：必须重新接收验证码。
- 管理员后台修改用户邮箱或手机号后，会清空对应验证状态。
- 邮箱找回密码需要后台基础信息中配置有效站点地址，用于生成重置链接。

## API 简介

API 路由前缀：`/api`

公开接口：

```text
POST /api/auth/login
POST /api/auth/register/send-code
POST /api/auth/register
```

登录后接口：

```text
POST /api/auth/logout
GET  /api/auth/user
POST /api/play-auth/videos/:id
POST /api/play-auth/lessons/:id
```

管理员 API：

```text
GET    /api/grants
GET    /api/grants/:id
POST   /api/grants
POST   /api/grants/:id
DELETE /api/grants/:id
```

认证方式：

```http
Authorization: Bearer <token>
```

## 安装包和发布

项目发布时通常提供三份包：

- `demo-site-tp6-web-installer-*.tar.gz`：网页安装版，适合宝塔 / LNMP。
- `demo-site-tp6-php-cli-*.tar.gz`：命令行安装版。
- `demo-site-tp6-docker-installer-*.tar.gz`：Docker Compose 安装版。

本地打包脚本：

```bash
./scripts/build-installer-package.sh
```

发布包输出目录：

```text
dist/
```

`dist/` 默认不提交到 Git。

## 升级维护

详细说明见 [UPGRADE.md](UPGRADE.md)。

推荐升级流程：

```text
备份数据 → 上传新版 → 覆盖代码 → 执行数据库升级 SQL → 清缓存 → 修权限 → 重载服务 → 验证功能
```

升级时不要覆盖或删除：

```text
.env
runtime/
public/uploads/
```

升级前建议备份：

```bash
tar -czf ../demo-site-tp6-files-backup-$(date +%Y%m%d-%H%M%S).tar.gz .env public/uploads
mysqldump -uroot -p tp6_demo > ../tp6_demo-backup-$(date +%Y%m%d-%H%M%S).sql
```

## 常见问题

### 首页 404

确认站点 root 指向：

```text
项目目录/public
```

并确认 ThinkPHP 伪静态已配置。

### 首页 403

通常是宝塔运行目录没有设置为 `/public`。

正确配置：

```text
网站目录：项目根目录
运行目录：/public
```

### 上传视频提示 413 Request Entity Too Large

调大 Nginx 和 PHP 上传限制：

```nginx
client_max_body_size 2048m;
```

```ini
upload_max_filesize = 2048M
post_max_size = 2050M
```

### 登录提示 JWT_SECRET 未配置

检查 `.env` 是否存在并包含：

```env
JWT_SECRET=...
PLAYBACK_SIGN_SECRET=...
```

### 上传目录 Permission denied

确保 PHP-FPM 用户可写：

```bash
chown -R www:www runtime public/uploads
chmod -R 775 runtime public/uploads
```

Ubuntu / Debian 可能使用：

```bash
chown -R www-data:www-data runtime public/uploads
chmod -R 775 runtime public/uploads
```

### 管理员密码登录不上

登录密码至少 6 位。如果安装时设置过短密码，可重置密码哈希：

```bash
php -r "echo password_hash('Admin123456', PASSWORD_DEFAULT), PHP_EOL;"
```

然后更新数据库：

```sql
UPDATE users SET password_hash = '这里粘贴生成的哈希' WHERE username = 'admin';
```

### fileinfo 是否必须安装

不是必装。没有 `fileinfo` 时会降低 MIME 识别精度，但不影响基础上传和播放流程。

### ffmpeg / ffprobe 是否必须安装

不是必装。缺失时主要影响自动识别视频时长、转码、截图等能力，不影响基础页面、登录、上传和播放。

## 开发建议

### 语法检查

如果本机没有 PHP，可用 Docker 容器检查：

```bash
docker compose exec -T php sh -lc 'find app route installer -name "*.php" -print0 | xargs -0 -n1 php -l'
```

### 查看路由

主要路由文件：

- [route/app.php](route/app.php)
- [route/api.php](route/api.php)

### 清理缓存

```bash
rm -rf runtime/cache/* runtime/temp/*
```

## 安全建议

- 生产环境关闭 `APP_DEBUG`。
- 使用强随机 `JWT_SECRET` 和 `PLAYBACK_SIGN_SECRET`。
- Web root 必须指向 `public`。
- 禁止外部访问 `.env`、`runtime/`、`vendor/` 等目录。
- 安装完成后删除 `public/install.php`。
- 不要把 `.env`、上传文件、运行日志提交到 Git。
- 本地视频建议禁止 `/uploads/videos/` 直连，通过 internal 路由播放。
- SMTP 密码、短信 API Key、Secret 留空保存时会保留旧值，避免在后台误清空。

## 许可证

本项目使用 MIT License，详见 [LICENSE](LICENSE)。
