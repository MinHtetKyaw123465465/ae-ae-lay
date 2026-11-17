<?php
namespace Tests\Feature;
use App\Models\{User,WebPushSubscription};
use App\Services\TelegramNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\{DB,Http,Log,Queue};
use Illuminate\Support\Str;
use Tests\TestCase;
class TelegramNotificationTest extends TestCase {
 use RefreshDatabase;
 private const TOKEN='123456789:TEST_ONLY_TELEGRAM_TOKEN';
 protected function setUp():void{parent::setUp();Queue::fake();Http::preventStrayRequests();config(['services.telegram.bot_token'=>self::TOKEN,'services.telegram.admin_chat_id'=>'123456789','app.url'=>'https://shop.example.com']);}
 private function user(string $role='agent'):User{$u=new User(['name'=>$role==='owner'?'ပိုင်ရှင်':'မသဇင် <စမ်းသပ်>','phone'=>'09'.random_int(100000000,999999999),'password'=>'TestingPassword!']);$u->forceFill(['role'=>$role,'status'=>'approved'])->save();return $u;}
 private function product():int{return DB::table('products')->insertGetId(['name'=>'FEG Rice Shampoo','description'=>'ပစ္စည်း','price'=>22000,'commission'=>6000,'stock'=>100,'active'=>true,'image'=>'/products/cream.jpg','created_at'=>now(),'updated_at'=>now()]);}
 private function body(int $product,array $changes=[]):array{return array_replace(['request_key'=>(string)Str::uuid(),'product_id'=>$product,'quantity'=>25,'customer'=>'PRIVATE CUSTOMER','phone'=>'09123456789','address'=>'အမှတ် ၂၄၊ ဗဟန်းမြို့နယ်၊ ရန်ကုန် <အိမ်>'],$changes);}
 public function test_committed_order_sends_product_quantity_agent_customer_phone_address_and_private_order_link_to_admin_once():void{
  Http::fake(['api.telegram.org/*'=>Http::response(['ok'=>true,'result'=>['message_id'=>1]],200)]);$o=$this->user('owner');$a=$this->user();$p=$this->product();$body=$this->body($p);$id=$this->actingAs($a)->postJson('/api/orders',$body)->assertCreated()->json('id');
  Http::assertSent(function($request)use($id){$text=$request['text'];$this->assertStringContainsString('FEG Rice Shampoo',$text);$this->assertStringContainsString('၂၅ ခု',$text);$this->assertStringContainsString('မသဇင် <စမ်းသပ်>',$text);$this->assertStringContainsString('အမှတ် ၂၄၊ ဗဟန်းမြို့နယ်၊ ရန်ကုန် <အိမ်>',$text);$this->assertStringContainsString('PRIVATE CUSTOMER',$text);$this->assertStringContainsString('09123456789',$text);$this->assertFalse(isset($request['parse_mode']));$this->assertSame('https://shop.example.com/?page=orders&order='.$id,$request['reply_markup']['inline_keyboard'][0][0]['url']);return $request->method()==='POST'&&$request->url()==='https://api.telegram.org/bot'.self::TOKEN.'/sendMessage'&&$request['chat_id']==='123456789';});
  $this->postJson('/api/orders',$body)->assertOk();Http::assertSentCount(1);$this->assertDatabaseCount('orders',1);$this->assertDatabaseHas('shop_notifications',['recipient_id'=>$o->id,'type'=>'order_created']);
 }
 public function test_no_telegram_message_is_sent_before_commit_or_after_rollback():void{
  Http::fake();$this->user('owner');$a=$this->user();$p=$this->product();DB::beginTransaction();try{$this->actingAs($a)->postJson('/api/orders',$this->body($p))->assertCreated();Http::assertNothingSent();}finally{DB::rollBack();}Http::assertNothingSent();$this->assertDatabaseCount('orders',0);
 }
 public function test_outer_commit_triggers_delivery_after_transaction_locks_are_released():void{
  Http::fake(['api.telegram.org/*'=>Http::response(['ok'=>true],200)]);$this->user('owner');$a=$this->user();$p=$this->product();DB::beginTransaction();$this->actingAs($a)->postJson('/api/orders',$this->body($p))->assertCreated();Http::assertNothingSent();DB::commit();Http::assertSentCount(1);
 }
 public function test_telegram_api_rejection_never_rolls_back_the_order_or_exposes_secrets_in_logs():void{
  Log::spy();Http::fake(['api.telegram.org/*'=>Http::response(['ok'=>false,'error_code'=>403,'description'=>'Private description'],403)]);$this->user('owner');$a=$this->user();$p=$this->product();$this->actingAs($a)->postJson('/api/orders',$this->body($p))->assertCreated();$this->assertDatabaseCount('orders',1);$this->assertSame(75,DB::table('products')->value('stock'));
  Log::shouldHaveReceived('warning')->withArgs(function($message,$context){$this->assertStringNotContainsString(self::TOKEN,json_encode($context));$this->assertStringNotContainsString('အမှတ်',json_encode($context,JSON_UNESCAPED_UNICODE));return $message==='Telegram delivery failed'&&$context['http_status']===403;});
 }
 public function test_network_exception_including_token_does_not_break_order_or_leak_its_message():void{
  Log::spy();Http::fake(fn()=>throw new ConnectionException('SECRET URL '.self::TOKEN));$this->user('owner');$a=$this->user();$p=$this->product();$this->actingAs($a)->postJson('/api/orders',$this->body($p))->assertCreated();$this->assertDatabaseCount('orders',1);
  Log::shouldHaveReceived('warning')->withArgs(fn($message,$context)=>$message==='Telegram transport unavailable'&&!str_contains(json_encode($context),self::TOKEN)&&$context['exception']===ConnectionException::class);
 }
 public function test_telegram_order_delivery_does_not_enqueue_a_second_browser_push():void{
  Http::fake(['api.telegram.org/*'=>Http::response(['ok'=>true],200)]);$o=$this->user('owner');$a=$this->user();$p=$this->product();WebPushSubscription::create(['user_id'=>$o->id,'endpoint_hash'=>hash('sha256','endpoint'),'endpoint'=>'https://fcm.googleapis.com/test','public_key'=>'key','auth_token'=>'auth']);$keys=\Minishlink\WebPush\VAPID::createVapidKeys();config(['webpush.public_key'=>$keys['publicKey'],'webpush.private_key'=>$keys['privateKey']]);
  $this->actingAs($a)->postJson('/api/orders',$this->body($p))->assertCreated();Http::assertSentCount(1);Queue::assertNothingPushed();
 }
 public function test_missing_configuration_and_failed_orders_make_no_telegram_request():void{
  Http::fake();config(['services.telegram.bot_token'=>'']);$this->user('owner');$a=$this->user();$p=$this->product();$this->actingAs($a)->postJson('/api/orders',$this->body($p))->assertCreated();Http::assertNothingSent();config(['services.telegram.bot_token'=>self::TOKEN]);$this->postJson('/api/orders',$this->body($p,['quantity'=>100]))->assertUnprocessable();Http::assertNothingSent();
 }
 public function test_agent_status_notices_are_not_sent_to_the_admin_telegram_chat():void{
  Http::fake(['api.telegram.org/*'=>Http::response(['ok'=>true],200)]);$o=$this->user('owner');$a=$this->user();$p=$this->product();$id=$this->actingAs($a)->postJson('/api/orders',$this->body($p))->assertCreated()->json('id');$this->actingAs($o)->withSession(['password_hash_web'=>$o->password])->patchJson('/api/orders/'.$id.'/status',['status'=>'sent'])->assertOk();Http::assertSentCount(1);$this->assertDatabaseHas('shop_notifications',['recipient_id'=>$a->id,'type'=>'order_sent']);
 }
 public function test_test_command_reports_success_and_missing_config_without_printing_token():void{
  Http::fake(['api.telegram.org/*'=>Http::response(['ok'=>true],200)]);$this->artisan('app:telegram-test')->expectsOutput('Telegram စမ်းသပ်စာ ပို့ပြီးပါပြီ။ Admin Telegram ကို စစ်ပေးပါ။')->assertSuccessful();Http::assertSentCount(1);config(['services.telegram.admin_chat_id'=>'']);$this->artisan('app:telegram-test')->assertFailed();Http::assertSentCount(1);
 }
 public function test_plain_text_message_with_maximum_unicode_address_fits_telegram_limit():void{
  Http::fake(['api.telegram.org/*'=>Http::response(['ok'=>true],200)]);$this->user('owner');$a=$this->user();$a->name=str_repeat('မ',100);$a->save();$p=$this->product();DB::table('products')->where('id',$p)->update(['name'=>str_repeat('က',150)]);$this->actingAs($a)->postJson('/api/orders',$this->body($p,['customer'=>str_repeat('🙋',100),'phone'=>str_repeat('9',20),'address'=>str_repeat('🏠',1000)]))->assertCreated();Http::assertSent(function($r){$this->assertLessThanOrEqual(4096,strlen(mb_convert_encoding($r['text'],'UTF-16LE','UTF-8'))/2);return true;});
 }
 public function test_no_insecure_order_button_is_emitted_and_token_url_injection_is_rejected():void{
  Http::fake(['api.telegram.org/*'=>Http::response(['ok'=>true],200)]);config(['app.url'=>'http://localhost']);$this->user('owner');$a=$this->user();$p=$this->product();$this->actingAs($a)->postJson('/api/orders',$this->body($p))->assertCreated();Http::assertSent(fn($r)=>!isset($r['reply_markup']));config(['services.telegram.bot_token'=>'https://attacker.example/token']);$this->assertFalse(app(TelegramNotifications::class)->configured());
 }
 public function test_log_storage_failure_cannot_turn_a_committed_order_into_an_error_response():void{
  Log::shouldReceive('warning')->andThrow(new \RuntimeException('Log storage unavailable'));Http::fake(['api.telegram.org/*'=>Http::response(['ok'=>false],503)]);$this->user('owner');$a=$this->user();$p=$this->product();$this->actingAs($a)->postJson('/api/orders',$this->body($p))->assertCreated();$this->assertDatabaseCount('orders',1);
 }

}
