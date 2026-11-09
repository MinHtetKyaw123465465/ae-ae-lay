<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\{User,WebPushSubscription};
use App\Services\{ShopNotifications,PushDelivery};
use App\Jobs\SendShopPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Auth,Queue};
use Minishlink\WebPush\VAPID;
use GuzzleHttp\{Client,HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
class NotificationsTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void{parent::setUp();Queue::fake();}
 private function user(string $role='agent',string $status='approved'):User{$u=User::create(['name'=>'စမ်းသပ်သူ','phone'=>'09'.random_int(100000000,999999999),'password'=>'TestingPassword!']);$u->forceFill(compact('role','status'))->save();return $u;}
 private function keys():array{$keys=VAPID::createVapidKeys();config(['webpush.public_key'=>$keys['publicKey'],'webpush.private_key'=>$keys['privateKey'],'webpush.subject'=>'https://example.com']);return $keys;}
 private function subscription(string $suffix='1'):array{$keys=VAPID::createVapidKeys();return ['endpoint'=>'https://fcm.googleapis.com/fcm/send/test-'.$suffix,'keys'=>['p256dh'=>$keys['publicKey'],'auth'=>rtrim(strtr(base64_encode(random_bytes(16)),'+/','-_'),'=')]];}
 private function device(User $u,array $body):WebPushSubscription{return WebPushSubscription::create(['user_id'=>$u->id,'endpoint_hash'=>hash('sha256',$body['endpoint']),'endpoint'=>$body['endpoint'],'public_key'=>$body['keys']['p256dh'],'auth_token'=>$body['keys']['auth']]);}
 private function note(User $u):int{return app(ShopNotifications::class)->record($u->id,'order_sent','အော်ဒါ ပို့ပြီးပါပြီ','အော်ဒါအသေးစိတ်တွင် ကြည့်ပါ။','orders',1);}
 public function test_remember_me_issues_a_secure_cookie_for_thirty_days_only_when_requested():void {
  $u=$this->user();$name=Auth::guard('web')->getRecallerName();$response=$this->postJson('/api/login',['phone'=>$u->phone,'password'=>'TestingPassword!','remember'=>true])->assertOk()->assertCookie($name);$cookie=$response->getCookie($name,false);$this->assertTrue($cookie->isHttpOnly());$this->assertTrue($cookie->isSecure());$this->assertEqualsWithDelta(time()+30*86400,$cookie->getExpiresTime(),120);$this->assertStringNotContainsString('TestingPassword!',$cookie->getValue());$this->postJson('/api/logout')->assertOk();$this->postJson('/api/login',['phone'=>$u->phone,'password'=>'TestingPassword!','remember'=>false])->assertOk()->assertCookieMissing($name);
 }
 public function test_notifications_are_scoped_paginated_and_read_only_by_the_recipient():void {
  $a=$this->user();$b=$this->user();for($i=0;$i<16;$i++)$this->note($a);$secret=$this->note($b);$list=$this->actingAs($a)->getJson('/api/notifications')->assertOk()->json();$this->assertCount(10,$list['data']);$this->assertSame(16,$list['unread_count']);$this->assertSame(16,$list['meta']['total']);$this->getJson('/api/notifications?page=2')->assertJsonCount(6,'data');$this->patchJson('/api/notifications/'.$secret.'/read')->assertNotFound();$this->patchJson('/api/notifications/'.$list['data'][0]['id'].'/read')->assertOk();$this->getJson('/api/notifications?summary=1')->assertJsonPath('unread_count',15)->assertJsonMissingPath('data');$through=$list['latest_id'];$this->note($a);$this->patchJson('/api/notifications/read-all',['through_id'=>$through])->assertOk();$this->getJson('/api/notifications?summary=1')->assertJsonPath('unread_count',1);
 }
 public function test_guest_and_pending_accounts_cannot_access_notification_or_push_data():void {
  foreach(['/api/notifications','/api/push/settings'] as $url)$this->getJson($url)->assertUnauthorized();$this->actingAs($this->user(status:'pending'))->getJson('/api/notifications')->assertForbidden();$this->getJson('/api/push/settings')->assertForbidden();
 }
 public function test_business_events_create_notices_once_even_when_order_or_payment_is_retried():void {
  $owner=$this->user('owner');$agent=$this->user();$product=DB::table('products')->insertGetId(['name'=>'ပစ္စည်း','description'=>'အသေးစိတ်','price'=>35000,'commission'=>5000,'stock'=>20,'active'=>true,'image'=>'/products/cream.jpg','created_at'=>now(),'updated_at'=>now()]);$body=['request_key'=>(string)\Illuminate\Support\Str::uuid(),'product_id'=>$product,'quantity'=>2,'customer'=>'PRIVATE CUSTOMER','phone'=>'09123456789','address'=>'PRIVATE ADDRESS'];$order=$this->actingAs($agent)->postJson('/api/orders',$body)->assertCreated()->json('id');$this->postJson('/api/orders',$body)->assertOk();$this->assertSame(1,DB::table('shop_notifications')->where('recipient_id',$owner->id)->count());$this->actingAs($owner)->withSession(['password_hash_web'=>$owner->password])->patchJson('/api/orders/'.$order.'/status',['status'=>'sent'])->assertOk();$this->patchJson('/api/orders/'.$order.'/status',['status'=>'sent'])->assertOk();$payment=['request_key'=>(string)\Illuminate\Support\Str::uuid(),'agent_id'=>$agent->id,'amount'=>10000,'reference'=>'private payment reference'];$this->postJson('/api/payouts',$payment)->assertCreated();$this->postJson('/api/payouts',$payment)->assertOk();$this->assertSame(2,DB::table('shop_notifications')->where('recipient_id',$agent->id)->count());$this->assertStringNotContainsString('PRIVATE',json_encode(DB::table('shop_notifications')->get()));$this->assertStringNotContainsString('09123456789',json_encode(DB::table('shop_notifications')->get()));
 }
 public function test_registration_and_approval_notify_the_correct_account():void {
  $owner=$this->user('owner');$this->postJson('/api/register',['name'=>'အသစ်','phone'=>'09912345678','password'=>'TestingPassword!'])->assertCreated();$new=User::where('phone','09912345678')->first();$this->assertDatabaseHas('shop_notifications',['recipient_id'=>$owner->id,'type'=>'agent_registered']);$this->actingAs($owner)->patchJson('/api/agents/'.$new->id,['status'=>'approved'])->assertOk();$this->patchJson('/api/agents/'.$new->id,['status'=>'approved'])->assertOk();$this->assertSame(1,DB::table('shop_notifications')->where('recipient_id',$new->id)->count());
 }
 public function test_push_subscription_rejects_ssrf_targets_and_invalid_keys():void {
  $this->keys();$u=$this->user();$valid=$this->subscription();$this->actingAs($u);foreach(['http://127.0.0.1/internal','https://169.254.169.254/','https://fcm.googleapis.com.attacker.example/send','https://user:pass@fcm.googleapis.com/send','https://fcm.googleapis.com:8080/send'] as $endpoint)$this->postJson('/api/push/subscribe',[...$valid,'endpoint'=>$endpoint])->assertUnprocessable();$this->postJson('/api/push/subscribe',[...$valid,'keys'=>['p256dh'=>'fake','auth'=>'fake']])->assertUnprocessable();$this->assertDatabaseCount('web_push_subscriptions',0);
 }
 public function test_push_subscriptions_are_encrypted_at_rest_and_capped_per_account():void {
  $this->keys();$u=$this->user();$this->actingAs($u);$first=$this->subscription();$this->postJson('/api/push/subscribe',$first)->assertOk();$raw=DB::table('web_push_subscriptions')->first();$this->assertNotSame($first['endpoint'],$raw->endpoint);$this->assertNotSame($first['keys']['p256dh'],$raw->public_key);for($i=2;$i<=10;$i++)$this->postJson('/api/push/subscribe',$this->subscription((string)$i))->assertOk();$this->postJson('/api/push/subscribe',$this->subscription('11'))->assertUnprocessable();$this->postJson('/api/push/status',['endpoint'=>$first['endpoint']])->assertJsonPath('enabled',true);$this->deleteJson('/api/push/subscribe',['endpoint'=>$first['endpoint']])->assertOk();$this->assertDatabaseCount('web_push_subscriptions',9);
 }
 public function test_logout_removes_only_the_current_device_subscription():void {
  $u=$this->user();$a=$this->subscription('a');$this->device($u,$a);$this->device($u,$this->subscription('b'));$this->actingAs($u)->postJson('/api/logout',['push_endpoint'=>$a['endpoint']])->assertOk();$this->assertDatabaseCount('web_push_subscriptions',1);$this->assertDatabaseMissing('web_push_subscriptions',['endpoint_hash'=>hash('sha256',$a['endpoint'])]);
 }
 public function test_rejecting_an_agent_removes_push_devices_and_delivery_is_skipped():void {
  $owner=$this->user('owner');$u=$this->user();$this->device($u,$this->subscription());$id=$this->note($u);$this->actingAs($owner)->patchJson('/api/agents/'.$u->id,['status'=>'rejected'])->assertOk();$this->assertDatabaseCount('web_push_subscriptions',0);$fake=\Mockery::mock(PushDelivery::class);$fake->shouldNotReceive('send');(new SendShopPush($id))->handle($fake);
 }
 public function test_web_push_payload_is_encrypted_and_expired_endpoints_are_pruned():void {
  $this->keys();$u=$this->user();$device=$this->device($u,$this->subscription());$id=$this->note($u);$handler=new MockHandler([new Response(201)]);$delivery=new PushDelivery(new Client(['handler'=>HandlerStack::create($handler)]));$notice=DB::table('shop_notifications')->where('id',$id)->first();$payload=['id'=>$id,'recipient_id'=>$u->id,'title'=>$notice->title,'body'=>$notice->body,'destination'=>'orders','resource_id'=>1];$result=$delivery->send($device,$payload);$this->assertTrue($result['success']);$this->assertFalse($result['expired']);$request=$handler->getLastRequest();$this->assertStringContainsString('vapid',$request->getHeaderLine('Authorization'));$this->assertStringNotContainsString($notice->title,(string)$request->getBody());
  $fake=\Mockery::mock(PushDelivery::class);$fake->shouldReceive('send')->once()->andReturn(['success'=>false,'expired'=>true,'status'=>410]);(new SendShopPush($id))->handle($fake);$this->assertDatabaseMissing('web_push_subscriptions',['id'=>$device->id]);
 }
 public function test_push_job_is_after_commit_and_rollback_does_not_leave_notifications():void {
  $this->keys();Queue::fake();$u=$this->user();$this->device($u,$this->subscription());$this->note($u);Queue::assertPushed(SendShopPush::class,fn($job)=>$job->afterCommit===true);$before=DB::table('shop_notifications')->count();DB::beginTransaction();$this->note($u);DB::rollBack();$this->assertSame($before,DB::table('shop_notifications')->count());
 }
}
