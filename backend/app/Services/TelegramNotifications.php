<?php
namespace App\Services;
use Illuminate\Support\Facades\{DB,Http,Log};
class TelegramNotifications {
 private ?string $failure=null;
 private ?int $curlError=null;
 public function configured():bool {
  $token=trim((string)config('services.telegram.bot_token',''));$chat=trim((string)config('services.telegram.admin_chat_id',''));
  return strlen($token)<=256&&strlen($chat)<=32&&preg_match('/^[0-9]+:[A-Za-z0-9_-]+$/D',$token)===1&&preg_match('/^[1-9][0-9]*$/D',$chat)===1;
 }
 private function warn(string $message,array $context):void {
  try{Log::warning($message,$context);}catch(\Throwable){} // Notification/log failures must not fail a committed order.
 }
 public function failureReason():?string{return $this->failure;}
 public function curlError():?int{return $this->curlError;}
 private function transportCode(\Throwable $error):?int {
  for($current=$error,$depth=0;$current!==null&&$depth<8;$current=$current->getPrevious(),$depth++){
   if(method_exists($current,'getHandlerContext')){$context=$current->getHandlerContext();$code=(int)($context['errno']??0);if($code>0)return $code;}
   // Read only the numeric code; the full message may contain the bot token.
   if(preg_match('/\bcURL error ([0-9]+):/',$current->getMessage(),$match))return (int)$match[1];
  }
  return null;
 }
 public function sendOrder(int $id):bool {
  try{
   if(!$this->configured()){$this->failure='not_configured';return false;}
   $order=DB::table('orders')->join('products','products.id','=','orders.product_id')->join('users','users.id','=','orders.agent_id')->where('orders.id',$id)->select('orders.id','orders.quantity','orders.customer','orders.phone','orders.address','products.name AS product','users.name AS agent')->first();
   if(!$order){$this->failure='order_not_found';return false;}
   $format=app(ShopNotifications::class);
   $text="🛍 အော်ဒါအသစ် #".$format->number($order->id)."\n\nပစ္စည်း — ".$order->product."\nအရေအတွက် — ".$format->number($order->quantity)." ခု\nကိုယ်စားလှယ် — ".$order->agent."\n\nဝယ်သူအမည် — ".$order->customer."\nဖုန်းနံပါတ် — ".$order->phone."\n\nပို့ဆောင်ရန်လိပ်စာ —\n".$order->address;
   $payload=['text'=>$text];$base=rtrim((string)config('app.url'),'/');
   if(str_starts_with($base,'https://')&&filter_var($base,FILTER_VALIDATE_URL))$payload['reply_markup']=['inline_keyboard'=>[[['text'=>'အော်ဒါကြည့်မည်','url'=>$base.'/?page=orders&order='.$id]]]];
   return $this->send($payload,$id);
  }catch(\Throwable $error){$this->failure='internal_error';$this->warn('Telegram notification failed',['order_id'=>$id,'exception'=>get_class($error)]);return false;}
 }
 public function sendTest():bool{return $this->send(['text'=>'အဲအဲလေး Telegram အသိပေးချက် ချိတ်ဆက်ပြီးပါပြီ။']);}
 private function send(array $payload,?int $order=null):bool {
  $this->failure=null;$this->curlError=null;if(!$this->configured()){$this->failure='not_configured';return false;}
  try{
   $payload['chat_id']=trim((string)config('services.telegram.admin_chat_id'));$payload['link_preview_options']=['is_disabled'=>true];
   // No parse_mode: names and delivery addresses are plain text, never markup.
   // Never retry an ambiguous timeout automatically; Telegram may already have sent it.
   $response=Http::asJson()->acceptJson()->connectTimeout(3)->timeout(5)->withoutRedirecting()->withOptions(config('services.telegram.force_ipv4',false)?['force_ip_resolve'=>'v4']:[])->post('https://api.telegram.org/bot'.trim((string)config('services.telegram.bot_token')).'/sendMessage',$payload);
   if($response->successful()&&$response->json('ok')===true)return true;
   $code=(int)$response->json('error_code',$response->status());$this->failure=match($code){401=>'invalid_token',403=>'chat_forbidden',400=>'invalid_chat',429=>'rate_limited',default=>'api_error'};
   $this->warn('Telegram delivery failed',['order_id'=>$order,'http_status'=>$response->status(),'telegram_error_code'=>$code]);return false;
  }catch(\Throwable $error){$this->curlError=$this->transportCode($error);$this->failure=match($this->curlError){5=>'proxy_dns_error',6=>'dns_error',7=>'connect_error',28=>'timeout',35,51,58,60,77,83,90,91=>'tls_error',default=>'transport_error'};
   // HTTP exception messages/URLs may contain the bot token. Do not log them.
   $this->warn('Telegram transport unavailable',['order_id'=>$order,'exception'=>get_class($error),'curl_errno'=>$this->curlError]);return false;
  }
 }
}
