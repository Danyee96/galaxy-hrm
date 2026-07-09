# Hostinger Deployment

This is a Laravel + Inertia + Vite application, not a standalone Vite static site.

## Why the Hostinger Build Failed

The Vite build is successful, but Laravel Vite writes compiled assets to:

```text
public/build
```

Hostinger's Vite preset expects the default Vite output folder:

```text
dist
```

That is why the build log ends with:

```text
ERROR: No output directory found after build
```

## If Using Hostinger's Build Screen

Open **Build and output settings** and set:

```text
Build command: npm run build
Output directory: public/build
Install command: npm install
```

This only deploys the frontend assets. The full HRM system still needs PHP, Composer dependencies, a database, and Laravel environment variables.

## Correct Full Laravel Deployment

Use Hostinger PHP/Laravel hosting instead of a static Vite deployment:

1. Upload the Laravel project files outside `public_html` if Hostinger allows it.
2. Put the contents of this app's `public` folder inside `public_html`.
3. Update `public_html/index.php` paths if the app folder is outside `public_html`.
4. Run:

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

5. Add Hostinger's Laravel scheduler cron. In hPanel, open **Websites > Advanced > Cron Jobs** and create a custom cron that runs every minute:

```bash
cd /home/YOUR_HOSTINGER_USER/path-to-laravel-app && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Replace `/home/YOUR_HOSTINGER_USER/path-to-laravel-app` with the directory that contains this project's `artisan` file. This cron is required for automatic system backups; the app schedules `system-backups:auto` twice daily at 00:00 and 12:00 in the configured backup timezone.

6. Set production `.env` values:

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=https://downtowntrafik.com
DB_CONNECTION=mysql
DB_HOST=...
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
```

Do not upload `public/hot`; it is only for local Vite dev server mode.
