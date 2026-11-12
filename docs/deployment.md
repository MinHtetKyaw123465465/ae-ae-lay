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

## ဓာတ်ပုံနှင့် အကောင့်ပြင်ဆင်မှု အပ်ဒိတ်

ဤအပ်ဒိတ်တွင် ဇယားအသစ်မလိုပါ။ အရင် migration များ အပြည့်အစုံ ရှိရမည်။
Frontend build နှင့် PHP ကုဒ်ကို တွဲတင်ပြီး worker ကို ပြန်စပါ။ ရှိပြီးသား `.env`၊
`APP_KEY` နှင့် `storage/app/private` ကို ထိန်းထားပါ။ ယခင်အမှားကြောင့် နမူနာပုံသာ
သိမ်းထားသော ပစ္စည်းများကို မူရင်းပုံ ပြန်ရွေးသိမ်းပေးရမည်။

VAPID သော့ မသတ်မှတ်ရသေးလျှင် ပိုင်ရှင်က ဆက်တင်မှ `စတင်ဖွင့်မည်` နှိပ်နိုင်သည်။
အက်ပ်မှ စတင်လျှင် `storage/app/private/push/vapid.enc` တွင် ဝှက်သိမ်းသည်။
ယင်းဖိုင်နှင့် APP_KEY ကို တစ်ပြိုင်နက် ထိန်းထားပါ။ ရှိပြီးသား environment သော့နှစ်ခုကို
ဆက်သုံးလျှင် ဖိုင်အသစ်မဖန်တီးပါ။ သော့တစ်ခုသာ သတ်မှတ်ထားလျှင် သို့မဟုတ် ဖိုင်ပျက်နေလျှင်
သော့ကို အလိုအလျောက် မလဲဘဲ အမှားပြသည်။ ဖုန်းအသိပေးချက်အတွက် queue worker ကို
ဆက်လက်လည်ပတ်စေရမည်။ အက်ပ်တွင်း ခေါင်းလောင်းစာရင်းသည် queue မလိုပါ။

## အက်ပ်ပိတ်ထားချိန် အသိပေးချက်နှင့် hosting cron

ပိုင်ရှင်၏ အော်ဒါအသိပေးချက်တွင် အော်ဒါအမှတ်၊ ပစ္စည်း၊ အရေအတွက်နှင့် ကိုယ်စားလှယ်အမည်
ပါသည်။ ကိုယ်စားလှယ်ထံ ပို့ပြီး/ပယ်ဖျက်ပြီး အခြေအနေနှင့် အော်ဒါပစ္စည်းအရေအတွက်ကို ပို့သည်။
ဝယ်သူအမည်၊ လိပ်စာနှင့် ဖုန်းနံပါတ် မပို့ပါ။ ရှည်လွန်းသော ပစ္စည်း/ကိုယ်စားလှယ်အမည်ကို
အသိပေးချက်အရွယ်အစားနှင့် ကိုက်အောင် အတိုချုံ့ပြသည်။

အပ်ဒိတ်တင်ပြီးနောက် ဖုန်းတိုင်း အက်ပ်ကို တစ်ကြိမ် ပြန်ဖွင့်ပါ။ အက်ပ်က လက်ရှိအကောင့်အမှတ်ကိုသာ
ဖုန်း၏ IndexedDB တွင် သိမ်းသည်။ Service worker သည် ထိုအမှတ်နှင့် ဖုန်း၏ လျှို့ဝှက် push endpoint
ကို စစ်ပြီးမှ အသိပေးချက်ပြသည်။ Login cookie သက်တမ်းကုန်ခြင်းကြောင့် မတားတော့ပါ။
အကောင့်ထွက်ခြင်း၊ အကောင့်ပြောင်းခြင်း၊ ဖုန်းချိတ်ဆက်မှု ပယ်ဖျက်ခြင်းနှင့် အကောင့်ရပ်ဆိုင်းခြင်းကို
ဆက်ကာကွယ်ထားသည်။ ခေါင်းလောင်းနှင့် အော်ဒါအသေးစိတ် API များသည် အကောင့်ဝင်ရန် ဆက်လိုအပ်သည်။

အမြဲလည်ပတ်သော Supervisor/systemd worker ရှိလျှင် ထို worker ကို ဆက်သုံးပြီး အပ်ဒိတ်တိုင်း
`php artisan queue:restart` ပြုလုပ်ပါ။ Shared hosting တွင် `nohup` process ရပ်တတ်လျှင်
**hPanel → Cron Jobs → Custom** မှ တစ်မိနစ်တစ်ကြိမ် အောက်ပါ command ကို သတ်မှတ်နိုင်သည်။
PHP 8.4 လမ်းကြောင်းကို hosting terminal တွင် အရင်စစ်ပါ။

```bash
cd ~/domains/k2studioapp.com/public_html/ae-ae-lay/backend && /opt/alt/php84/usr/bin/php artisan app:work-notifications >> storage/logs/notification-worker.log 2>&1
```

ဤနေရာတွင် hPanel အချိန်အကွက်အားလုံးကို `*` ရွေးပါ။ SSH တွင် တစ်ကြိမ် run ရုံနှင့် cron
အလိုအလျောက် မဖန်တီးပါ။ Command သည် အလုပ်များကို စက္ကန့် ၅၀ ခန့် စောင့်ယူပို့ပြီး ပြန်ပိတ်ကာ
နောက်မိနစ်တွင် cron က ပြန်စသည်။ အရင် process မပြီးသေးလျှင် ထပ်မစအောင် ဖိုင် lock ဖြင့် ကာကွယ်သည်။
ကြာမြင့်နေသော အလုပ်တစ်ခုကြောင့် process သက်တမ်း ပိုရှည်နိုင်ပြီး နောက် cron သည် ထပ်မစပါ။
အမြဲလည်ပတ်သော worker နှင့် cron နည်းလမ်း နှစ်မျိုးလုံးကို တမင်ဖွင့်ထားရန် မလိုပါ။
Cron ကြားကာလနှင့် hosting ကန့်သတ်ချက်ကြောင့် အသိပေးချက် ခဏနောက်ကျနိုင်သည်။

စစ်ဆေးရန်—

```bash
php artisan queue:failed
php artisan app:work-notifications --max-time=10
```

Worker မလည်လျှင် ခေါင်းလောင်းစာရင်း ရောက်နိုင်သော်လည်း ဖုန်း push မရောက်ပါ။ ဖုန်း၏ အသိပေးချက်ခွင့်ပြုမှု၊
အင်တာနက်နှင့် OS/battery ကန့်သတ်ချက်များလည်း အကျုံးဝင်သည်။ `storage/logs` နှင့် worker log ကို စစ်ပါ။
