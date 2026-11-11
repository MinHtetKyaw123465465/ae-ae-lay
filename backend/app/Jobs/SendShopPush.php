<?php
namespace App\Jobs;
use App\Models\WebPushSubscription;
use App\Services\PushDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\{DB,Log};
class SendShopPush implements ShouldQueue {
 use Queueable;
 public int $tries=3;
 public int $timeout=60;
 public function __construct(public int $notificationId){}
 public function backoff():array{return [30,120];}
 public function handle(PushDelivery $delivery):void {
  $notice=DB::table('shop_notifications')->where('id',$this->notificationId)->first();if(!$notice)return;
  $account=DB::table('users')->where('id',$notice->recipient_id)->first(['status']);if(!$account||$account->status!=='approved')return;
  if(!app(\App\Services\PushKeys::class)->configured())return;
  $payload=['id'=>$notice->id,'recipient_id'=>$notice->recipient_id,'title'=>$notice->title,'body'=>$notice->body,'destination'=>$notice->destination,'resource_id'=>$notice->resource_id];$retry=false;
  foreach(WebPushSubscription::where('user_id',$notice->recipient_id)->orderBy('id')->limit(10)->get() as $device){
   try{$result=$delivery->send($device,$payload);if($result['expired']){$device->delete();continue;}if(!$result['success']){Log::warning('Web push failed',['notification_id'=>$notice->id,'status'=>$result['status']]);if(!$result['status']||$result['status']>=500||$result['status']===429)$retry=true;}}
   catch(\Throwable){$retry=true;Log::warning('Web push transport unavailable',['notification_id'=>$notice->id]);}
  }
  if($retry)throw new \RuntimeException('Web push transport unavailable; retrying.');
 }
}
