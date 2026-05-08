#!/bin/bash
set -e

if docker compose version >/dev/null 2>&1; then
  COMPOSE="docker compose"
else
  COMPOSE="docker-compose"
fi

echo "Starting Docker install environment..."

$COMPOSE up -d --build

echo ""
echo "Docker environment is starting!"
echo "  - Install page: http://localhost:8080/install.php"
echo "  - Site: http://localhost:8080"
echo "  - MySQL: localhost:3307"
echo ""
echo "Installer database values:"
echo "  - Host: mysql"
echo "  - Port: 3306"
echo "  - Database: tp6_demo"
echo "  - User: tp6_user"
echo "  - Password: tp6_pass"
echo ""
echo "To view logs: $COMPOSE logs -f"
echo "To stop: $COMPOSE down"
