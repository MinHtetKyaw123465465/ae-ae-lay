<?php
namespace Tests\Feature;
use App\Models\{User,WebPushSubscription};
use App\Services\ShopNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Queue};
use Tests\TestCase;
class NotificationDeliveryTest extends TestCase {
 use RefreshDatabase;
 private function user(string $role='agent'):User{$u=new User(['name'=>$role==='owner'?'ပိုင်ရှင်':'မသဇင်','phone'=>'09'.random_int(100000000,999999999),'password'=>'TestingPassword!']);$u->forceFill(['role'=>$role,'status'=>'approved'])->save();return $u;}
 private function device(User $u,string $suffix='one'):string{$endpoint='https://fcm.googleapis.com/fcm/send/private-'.$suffix;WebPushSubscription::create(['user_id'=>$u->id,'endpoint_hash'=>hash('sha256',$endpoint),'endpoint'=>$endpoint,'public_key'=>'key','auth_token'=>'auth']);return $endpoint;}
 public function test_order_notices_include_product_quantity_agent_and_respective_status_without_customer_details():void{
  Queue::fake();$o=$this->user('owner');$a=$this->user();$p=DB::table('products')->insertGetId(['name'=>'FEG Rice Shampoo','description'=>'ပစ္စည်း','price'=>22000,'commission'=>6000,'stock'=>100,'active'=>true,'image'=>'/products/cream.jpg','created_at'=>now(),'updated_at'=>now()]);
  $body=['product_id'=>$p,'quantity'=>25,'customer'=>'PRIVATE CUSTOMER','phone'=>'09123456789','address'=>'PRIVATE ADDRESS'];$id=$this->actingAs($a)->postJson('/api/orders',$body)->assertCreated()->json('id');$notice=DB::table('shop_notifications')->where('recipient_id',$o->id)->first();$this->assertStringContainsString('FEG Rice Shampoo',$notice->body);$this->assertStringContainsString('၂၅ ခု',$notice->body);$this->assertStringContainsString('မသဇင်',$notice->body);
  $this->actingAs($o)->withSession(['password_hash_web'=>$o->password])->patchJson('/api/orders/'.$id.'/status',['status'=>'sent'])->assertOk();$sent=DB::table('shop_notifications')->where('recipient_id',$a->id)->where('type','order_sent')->first();$this->assertSame('အော်ဒါ ပို့ပြီးပါပြီ',$sent->title);$this->assertStringContainsString('FEG Rice Shampoo',$sent->body);$this->assertStringContainsString('၂၅ ခု',$sent->body);
  $cancel=$this->postJson('/api/orders',[...$body,'agent_id'=>$a->id,'quantity'=>3])->assertCreated()->json('id');$this->patchJson('/api/orders/'.$cancel.'/status',['status'=>'cancelled'])->assertOk();$cancelled=DB::table('shop_notifications')->where('recipient_id',$a->id)->where('type','order_cancelled')->first();$this->assertSame('အော်ဒါ ပယ်ဖျက်ထားသည်',$cancelled->title);$this->assertStringContainsString('၃ ခု',$cancelled->body);$json=json_encode(DB::table('shop_notifications')->get());$this->assertStringNotContainsString('PRIVATE',$json);$this->assertStringNotContainsString('09123456789',$json);
 }
 public function test_long_names_do_not_overflow_the_notification_body_column():void{$body=app(ShopNotifications::class)->orderBody(str_repeat('က',150),100000,123456789,str_repeat('မ',100));$this->assertLessThanOrEqual(250,mb_strlen($body));$this->assertStringContainsString('၁၀၀,၀၀၀ ခု',$body);$this->assertStringContainsString('ကိုယ်စားလှယ်',$body);}
 public function test_device_verification_needs_no_session_but_requires_exact_device_recipient_and_approval():void{
  $u=$this->user();$b=$this->user();$endpoint=$this->device($u);$this->postJson('/api/push/verify',['endpoint'=>$endpoint,'recipient_id'=>$u->id])->assertOk()->assertExactJson(['allowed'=>true]);$this->postJson('/api/push/verify',['endpoint'=>$endpoint,'recipient_id'=>$b->id])->assertExactJson(['allowed'=>false]);$this->postJson('/api/push/verify',['endpoint'=>'wrong','recipient_id'=>$u->id])->assertExactJson(['allowed'=>false]);
  $u->forceFill(['status'=>'rejected'])->save();$this->postJson('/api/push/verify',['endpoint'=>$endpoint,'recipient_id'=>$u->id])->assertExactJson(['allowed'=>false]);$this->postJson('/api/push/verify',[])->assertUnprocessable();
 }
 public function test_explicit_logout_revokes_only_the_signed_out_push_device():void{
  $u=$this->user();$one=$this->device($u);$two=$this->device($u,'two');$this->actingAs($u)->postJson('/api/logout',['push_endpoint'=>$one])->assertOk();$this->postJson('/api/push/verify',['endpoint'=>$one,'recipient_id'=>$u->id])->assertExactJson(['allowed'=>false]);$this->postJson('/api/push/verify',['endpoint'=>$two,'recipient_id'=>$u->id])->assertExactJson(['allowed'=>true]);
 }
 public function test_cron_worker_skips_overlapping_runs_and_rejects_unbounded_runtime():void{
  $lock=fopen(storage_path('framework/notification-worker.lock'),'c');flock($lock,LOCK_EX);try{$this->artisan('app:work-notifications')->expectsOutput('Worker လည်ပတ်နေပြီးပါပြီ။')->assertSuccessful();}finally{flock($lock,LOCK_UN);fclose($lock);}
  $this->artisan('app:work-notifications',['--max-time'=>0])->assertFailed();$this->artisan('app:work-notifications',['--max-time'=>51])->assertFailed();
 }
 public function test_cron_worker_runs_the_database_queue_and_exits_without_a_permanent_process():void{config(['queue.default'=>'database']);$this->artisan('app:work-notifications',['--max-time'=>1])->assertSuccessful();}
 public function test_notification_task_is_scheduled_every_minute_with_bounded_runtime_and_overlap_protection():void{
  $events=app(\Illuminate\Console\Scheduling\Schedule::class)->events();$matches=array_values(array_filter($events,fn($e)=>str_contains($e->command??'','app:work-notifications')));
  $this->assertCount(1,$matches);$event=$matches[0];$this->assertSame('* * * * *',$event->expression);$this->assertStringContainsString('--max-time=45',$event->command);$this->assertTrue($event->withoutOverlapping);$this->assertSame(2,$event->expiresAt);$this->assertSame(storage_path('logs/notification-worker.log'),$event->output);$this->assertFalse($event->runInBackground);
 }

}
