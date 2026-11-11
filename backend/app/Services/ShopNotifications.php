<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use App\Jobs\SendShopPush;
class ShopNotifications {
 public function record(int $recipient,string $type,string $title,string $body,string $destination,?int $resource=null):int {
  $id=DB::table('shop_notifications')->insertGetId(['recipient_id'=>$recipient,'type'=>$type,'title'=>$title,'body'=>$body,'destination'=>$destination,'resource_id'=>$resource,'created_at'=>now(),'updated_at'=>now()]);
  if(app(PushKeys::class)->configured()&&DB::table('web_push_subscriptions')->where('user_id',$recipient)->exists())SendShopPush::dispatch($id)->afterCommit();
  return $id;
 }
 public function owner(string $type,string $title,string $body,string $destination,?int $resource=null):void {
  $id=DB::table('users')->where('role','owner')->where('status','approved')->value('id');if($id)$this->record($id,$type,$title,$body,$destination,$resource);
 }
}
