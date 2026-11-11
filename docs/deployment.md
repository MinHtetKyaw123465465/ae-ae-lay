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

## ကိုယ်စားလှယ် အစလက်ကျန် ပြောင်းလဲမှု တင်ခြင်း

၂၀၂၆-၁၁-၁၁ migration သည် `users` တွင် အစလက်ကျန်နှင့် စာရင်းဗားရှင်း ကော်လံများ၊
`agent_changes` ပြင်ဆင်မှုမှတ်တမ်းဇယားကို ထည့်သည်။ ရှိပြီးသား အော်ဒါနှင့် ငွေရှင်းမှတ်တမ်းများကို
မပြောင်းပါ။ အစလက်ကျန်မထည့်ရသေးသူများသည် ယခင်အတိုင်း ဆက်တွက်မည်။
ဖိုင်အသစ်များနှင့် frontend build ကို တစ်ပြိုင်နက် တင်ပြီး အထက်ပါ `migrate --force`
နှင့် cache/worker ပြန်စသည့် အဆင့်များကို ဆောင်ရွက်ပါ။ လုပ်ငန်းဒေတာတွင် `migrate:fresh`
သို့မဟုတ် seeder မသုံးပါနှင့်။

တင်ပြီးနောက် ပိုင်ရှင်အကောင့်ဖြင့် ကိုယ်စားလှယ်စုစုပေါင်း၊ ပြင်ဆင်ဖောင်၊ အစလက်ကျန်နှင့်
ပြင်ဆင်မှုမှတ်တမ်းကို စစ်ပါ။ အရင်ရောင်းအားကို `အားလုံး` အဆင့်စာရင်းတွင်သာ ပေါင်းတွက်သည်။
ကော်မရှင်အစလက်ကျန်သည် အရင်ပေးပြီးငွေ နုတ်ထားသော ကျန်ငွေဖြစ်ရမည်။
