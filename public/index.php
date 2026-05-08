<?php

use think\App;

$autoload = __DIR__ . '/../vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(503);
    echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><title>依赖未安装</title><style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;background:#f6f7fb;color:#172033;padding:40px}.card{max-width:760px;background:#fff;border:1px solid #e8ebf1;border-radius:16px;padding:24px;box-shadow:0 8px 24px rgba(15,23,42,.06)}code{background:#f1f5f9;padding:3px 6px;border-radius:6px}</style></head><body><div class="card"><h1>ThinkPHP 依赖未安装</h1><p>站点入口已经生效，不再是 Nginx File not found。现在需要在项目根目录安装 Composer 依赖：</p><p><code>docker exec tp6-php sh -lc "composer install --no-interaction --prefer-dist"</code></p><p>如果 Composer 下载仍提示代理 <code>127.0.0.1:10808</code> 连接失败，请先修复 Docker 容器的网络/代理配置。</p></div></body></html>';
    exit;
}

require $autoload;

$http = (new App())->http;
$response = $http->run();
$response->send();
$http->end($response);
