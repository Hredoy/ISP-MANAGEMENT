#!/usr/bin/env bash
set -euo pipefail

cd /var/www/isp-os/current

git fetch origin staging
git reset --hard origin/staging

composer install --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build

php artisan migrate --force
php artisan tenants:migrate
php artisan db:seed --class=RolePermissionSeeder --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
