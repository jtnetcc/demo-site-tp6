#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_NAME="demo-site-tp6"
STAMP="$(date +%Y%m%d-%H%M%S)"
DIST_DIR="$ROOT_DIR/dist"
PACKAGE="$DIST_DIR/${APP_NAME}-installer-${STAMP}.tar.gz"

mkdir -p "$DIST_DIR"

cd "$ROOT_DIR/.."

tar -czf "$PACKAGE" \
  --exclude="$APP_NAME/.env" \
  --exclude="$APP_NAME/.env.installer.generated" \
  --exclude="$APP_NAME/runtime" \
  --exclude="$APP_NAME/public/uploads/*" \
  --exclude="$APP_NAME/dist" \
  --exclude="$APP_NAME/.git" \
  --exclude="$APP_NAME/.DS_Store" \
  --exclude="$APP_NAME/node_modules" \
  --exclude="$APP_NAME/*.log" \
  --exclude="$APP_NAME/PROMPTS.md" \
  --exclude="$APP_NAME/THINKPHP_REWRITE_PROMPT.md" \
  --exclude="$APP_NAME/THINKPHP_REWRITE_PROMPT_COMPACT.md" \
  --exclude="$APP_NAME/THINKPHP_REWRITE_PROMPT_SPLIT.md" \
  "$APP_NAME/app" \
  "$APP_NAME/config" \
  "$APP_NAME/route" \
  "$APP_NAME/public" \
  "$APP_NAME/sql" \
  "$APP_NAME/vendor" \
  "$APP_NAME/installer" \
  "$APP_NAME/install.php" \
  "$APP_NAME/composer.json" \
  "$APP_NAME/composer.lock" \
  "$APP_NAME/think" \
  "$APP_NAME/INSTALL_LNMP.md"

echo "安装包已生成：$PACKAGE"
echo "宝塔安装：上传解压后，将站点运行目录设置为 /public，然后访问 http://服务器IP/install.php"
echo "命令行备用：tar -xzf $(basename "$PACKAGE") && cd $APP_NAME && php install.php"
