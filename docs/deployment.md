# Ae Ae Business deployment

Live URL: https://aeaebusiness.k2studioapp.com

Set the subdomain document root to the repository's `backend/public` directory.
Keep the complete `backend` directory on the server, including `app`, `bootstrap`,
`config`, `routes`, `storage`, `vendor`, and the private `.env` file. Public build
files alone cannot run the API. Apache must allow this directory's `.htaccess`
and enable URL rewriting. Enable HTTPS and the hosting panel's HTTPS redirect.

The frontend uses `/api` on the same domain and assets at `/assets`, so no separate
frontend URL, CORS configuration, or `/backend/public` URL prefix is needed.
The `.htaccess` file uses `index.php` as the directory index so Laravel serves
the built frontend and applies its response headers.

Use PHP 8.4.1 or newer with the extensions required by Composer. From `backend`,
install the locked production dependencies:

```sh
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
```

Merge `backend/.env.production.example` into the server's existing `backend/.env`.
Set the real database name, username, password, host, and port. Preserve the
existing `APP_KEY` and VAPID keys. For a new installation only, copy the template
to `.env` and run `php artisan key:generate` once.

The template sets the production URL, application name, secure session cookie,
Myanmar timezone, push subject, and disables debug output. Keep `VITE_API_URL`
unset when building, or set it to `/api`.

After updating the code and environment, run from `backend`:

```sh
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan queue:restart
```

Allow the PHP process to write to `storage` and `bootstrap/cache`. Keep a queue
worker running for push delivery:

```sh
php artisan queue:work --sleep=3 --tries=3 --timeout=60
```

For a new installation, run `php artisan app:create-owner` to create the owner.
If enabling push for the first time, run `php artisan app:generate-push-keys`,
then refresh the configuration cache. Existing installations keep their keys.

Build locally from the repository root with `npm run build`. Commit the generated
`backend/public` files together with the source changes. Node.js and Vite do not
need to run on the live server. Check `/`, `/api/csrf`, and login after deployment.
