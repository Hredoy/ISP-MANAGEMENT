# ISP OS Infra

Target environments:

- `main`: production, protected, CI required.
- `staging`: staging server, auto-deploy on merge.
- `dev`: active development.
- `feature/*`: feature work, for example `feature/mikrotik-api`.
- `fix/*`: fixes, for example `fix/bkash-parser`.

Staging should run on a separate VPS or subdomain such as `staging.yourplatform.com`, mirror production config, and seed 3 fake ISP tenants with 100 fake clients each before QA.

Production deploy sequence:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan tenants:migrate
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```
