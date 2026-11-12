<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

\Illuminate\Support\Facades\Artisan::command('app:create-owner',function(){
 if(\App\Models\User::where('role','owner')->exists()){$this->error('လုပ်ငန်းပိုင်ရှင် ရှိပြီးဖြစ်ပါသည်။');return 1;}
 $name=$this->ask('ပိုင်ရှင်အမည်');$phone=$this->ask('ဖုန်းနံပါတ်');$password=$this->secret('စကားဝှက် (အနည်းဆုံး ၈ လုံး)');
 $v=\Illuminate\Support\Facades\Validator::make(compact('name','phone','password'),['name'=>'required|string|max:100','phone'=>['required','regex:/^09[0-9]{7,11}$/','unique:users,phone'],'password'=>['required','string','min:8','max:128',new \App\Rules\SafePassword]]);
 if($v->fails()){$this->error($v->errors()->first());return 1;}
 $u=\App\Models\User::create(compact('name','phone','password'));$u->forceFill(['role'=>'owner','status'=>'approved'])->save();$this->info('ပိုင်ရှင်အကောင့် ဖန်တီးပြီးပါပြီ။');
})->purpose('Create the single shop owner securely');

Artisan::command('app:reset-password',function(){
 $phone=$this->ask('အကောင့်၏ ဖုန်းနံပါတ်');$u=\App\Models\User::where('phone',$phone)->first();if(!$u){$this->error('အကောင့် မတွေ့ပါ။');return 1;}
 $password=$this->secret('စကားဝှက်အသစ် (အနည်းဆုံး ၁၀ လုံး)');if(!is_string($password)||mb_strlen($password)<10||strlen($password)>72||str_contains($password,"\0")){$this->error('စကားဝှက် အရှည် မမှန်ပါ။');return 1;}
 $u->password=$password;$u->save();$this->info('စကားဝှက် ပြောင်းပြီးပါပြီ။');
})->purpose('Reset an account password from the trusted server console');

Artisan::command('app:prune-expired-cache',function(){
 if(config('cache.default')!=='database'){$this->info('Database cache is not enabled.');return;}
 $connection=config('cache.stores.database.connection');$store=\Illuminate\Support\Facades\DB::connection($connection);
 $count=$store->table(config('cache.stores.database.table','cache'))->where('expiration','<',time())->delete();
 \Illuminate\Support\Facades\DB::connection(config('cache.stores.database.lock_connection')??$connection)->table(config('cache.stores.database.lock_table')?:'cache_locks')->where('expiration','<',time())->delete();
 $this->info('Expired cache entries removed: '.$count);
})->purpose('Remove expired cache data without touching orders or accounts');
\Illuminate\Support\Facades\Schedule::command('app:prune-expired-cache')->daily()->withoutOverlapping();

// Shared hosting needs only one cron entry: php artisan schedule:run.
\Illuminate\Support\Facades\Schedule::command('app:work-notifications --max-time=45')
 ->everyMinute()->withoutOverlapping(2)
 ->appendOutputTo(storage_path('logs/notification-worker.log'));

Artisan::command('app:generate-push-keys {--force}',function(){
 if(app(\App\Services\PushKeys::class)->configured()&&!$this->option('force')){$this->info('အသိပေးချက်သော့ ရှိပြီးပါပြီ။');return;}
 $keys=\Minishlink\WebPush\VAPID::createVapidKeys();$file=base_path('.env');$env=file_get_contents($file);
 foreach(['VAPID_PUBLIC_KEY'=>$keys['publicKey'],'VAPID_PRIVATE_KEY'=>$keys['privateKey']] as $name=>$value){$pattern='/^'.preg_quote($name,'/').'=.*$/m';$line=$name.'='.$value;$env=preg_match($pattern,$env)?preg_replace($pattern,$line,$env):$env."\n".$line."\n";}
 file_put_contents($file,$env,LOCK_EX);chmod($file,0600);if($this->option('force'))\App\Models\WebPushSubscription::query()->delete();$this->call('config:clear');$this->info('အသိပေးချက်သော့ ဖန်တီးပြီးပါပြီ။');
})->purpose('Generate private VAPID keys without printing secrets');
