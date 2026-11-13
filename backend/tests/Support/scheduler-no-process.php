<?php
use App\Models\{User,WebPushSubscription};
use App\Services\PushDelivery;
use App\Jobs\SendShopPush;
use Illuminate\Support\Facades\{DB,Artisan};
use Minishlink\WebPush\VAPID;
use Symfony\Component\Console\Output\BufferedOutput;
// Integration probe: private test database only; never contacts a push provider.
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try{
if(config('database.connections.'.config('database.default').'.database')!=='aeae_testing')throw new RuntimeException('Probe requires aeae_testing.');
if(function_exists('proc_open'))throw new RuntimeException('Run with -d disable_functions=proc_open.');
$keys=VAPID::createVapidKeys();config(['queue.default'=>'database','webpush.public_key'=>$keys['publicKey'],'webpush.private_key'=>$keys['privateKey']]);
$delivery=new class extends PushDelivery {
 public int $calls=0;
 public function __construct(){}
 public function send(WebPushSubscription $device,array $payload):array{$this->calls++;return ['success'=>true,'expired'=>false,'status'=>201];}
};
$app->instance(PushDelivery::class,$delivery);
$agent=new User(['name'=>'SCHEDULER-PROBE','phone'=>'099'.random_int(10000000,99999999),'password'=>'ProbeOnly2026!']);$agent->forceFill(['role'=>'agent','status'=>'approved'])->save();
$notice=null;$job=null;$uuid=null;
try{
 $endpoint='https://fcm.googleapis.com/fcm/send/probe-'.bin2hex(random_bytes(12));
 WebPushSubscription::create(['user_id'=>$agent->id,'endpoint_hash'=>hash('sha256',$endpoint),'endpoint'=>$endpoint,'public_key'=>$keys['publicKey'],'auth_token'=>rtrim(strtr(base64_encode(random_bytes(16)),'+/','-_'),'=')]);
 $notice=DB::table('shop_notifications')->insertGetId(['recipient_id'=>$agent->id,'type'=>'probe','title'=>'Scheduler probe','body'=>'Test transport only','destination'=>'dashboard','created_at'=>now(),'updated_at'=>now()]);
 $job=$app['queue']->connection('database')->push(new SendShopPush($notice));$payload=DB::table('jobs')->where('id',$job)->value('payload');$uuid=json_decode($payload,true)['uuid'];
 $output=new BufferedOutput();Artisan::call('schedule:run',['-v'=>true],$output);echo $output->fetch();
 if($delivery->calls!==1||DB::table('jobs')->where('id',$job)->exists())throw new RuntimeException('Scheduler did not process the probe job exactly once.');
 echo "PASS: proc_open disabled; real database job processed once; controlled push transport invoked; no external delivery.\n";
}finally{
 if($job)DB::table('jobs')->where('id',$job)->delete();if($uuid)DB::table('failed_jobs')->where('uuid',$uuid)->delete();
 WebPushSubscription::where('user_id',$agent->id)->delete();if($notice)DB::table('shop_notifications')->where('id',$notice)->delete();$agent->delete();
}

}catch(\Throwable $error){fwrite(STDERR,"Probe failed: ".get_class($error)."\n");exit(1);}
