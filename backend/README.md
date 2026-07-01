# Backend

Laravel API/backend currently lives at the repository root. This folder reserves the target `isp-os/backend` boundary for the monorepo split once the frontend, mobile app, SMS reader, and agent are scaffolded.

Current backend commands:

```bash
composer install
php artisan migrate --force
php artisan tenants:migrate
php artisan db:seed --class=RolePermissionSeeder
php artisan config:cache
```
