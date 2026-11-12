<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Jobs\SendShopPush;
class ShopNotifications {
 public function number(int $number):string{return strtr(number_format($number),['0'=>'၀','1'=>'၁','2'=>'၂','3'=>'၃','4'=>'၄','5'=>'၅','6'=>'၆','7'=>'၇','8'=>'၈','9'=>'၉']);}
 public function orderBody(string $product,int $quantity,int $order,?string $agent=null):string {
  $body='#'.$this->number($order).' · '.Str::limit($product,$agent===null?150:130,'…').' · '.$this->number($quantity).' ခု';
  if($agent!==null)$body.="\nကိုယ်စားလှယ် — ".Str::limit($agent,75,'…');
  return mb_substr($body,0,250);
 }
 public function record(int $recipient,string $type,string $title,string $body,string $destination,?int $resource=null):int {
  $id=DB::table('shop_notifications')->insertGetId(['recipient_id'=>$recipient,'type'=>$type,'title'=>$title,'body'=>$body,'destination'=>$destination,'resource_id'=>$resource,'created_at'=>now(),'updated_at'=>now()]);
  if(app(PushKeys::class)->configured()&&DB::table('web_push_subscriptions')->where('user_id',$recipient)->exists())SendShopPush::dispatch($id)->afterCommit();
  return $id;
 }
 public function owner(string $type,string $title,string $body,string $destination,?int $resource=null):void {
  $id=DB::table('users')->where('role','owner')->where('status','approved')->value('id');if($id)$this->record($id,$type,$title,$body,$destination,$resource);
 }
}
