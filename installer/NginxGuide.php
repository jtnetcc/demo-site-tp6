<?php

namespace Installer;

class NginxGuide
{
    private string $root;

    public function __construct(string $root)
    {
        $this->root = $root;
    }

    public function render(): string
    {
        $public = $this->root . '/public';
        $videos = $this->root . '/public/uploads/videos/';

        return <<<NGINX

Nginx 配置示例（请按服务器 PHP-FPM 地址调整 fastcgi_pass）：

server {
    listen 80;
    server_name example.com;

    root {$public};
    index index.php index.html;

    client_max_body_size 2048m;

    location ^~ /uploads/videos/ {
        deny all;
    }

    location ^~ /internal-videos/ {
        internal;
        alias {$videos};
    }

    location / {
        try_files \$uri \$uri/ "/index.php?s=\$uri&\$query_string";
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}

宝塔提示：网站目录选择项目根目录，运行目录选择 /public；PHP 版本建议 8.1 或 8.2。

NGINX;
    }
}
