<?php
namespace Tests\Feature;
use App\Models\{User,WebPushSubscription};
use App\Services\PushKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Hash,Queue,Storage};
use Minishlink\WebPush\VAPID;
use Tests\TestCase;
class AccountAndPushSetupTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void{parent::setUp();Queue::fake();Storage::fake('local');config(['webpush.public_key'=>null,'webpush.private_key'=>null,'webpush.subject'=>'https://example.com']);}
 private function user(string $role='agent',string $status='approved'):User{$u=new User(['name'=>'စမ်းသပ်သူ','phone'=>'09'.random_int(100000000,999999999),'password'=>'TestingPassword!']);$u->forceFill(compact('role','status'))->save();return $u->refresh();}
 private function profile(User $u,array $changes=[]):array{return [...['name'=>'အမည်အသစ်','phone'=>'09987654321','current_password'=>'TestingPassword!','agent_version'=>$u->agent_version],...$changes];}
 private function reset(User $u,array $changes=[]):array{return [...['current_password'=>'TestingPassword!','password'=>'NewAgentPassword2026!','password_confirmation'=>'NewAgentPassword2026!','agent_version'=>$u->agent_version],...$changes];}
 public function test_users_can_only_edit_their_own_profile_and_phone_remains_unique():void{
  $u=$this->user();$b=$this->user();$this->actingAs($u)->putJson('/api/account',$this->profile($u,['phone'=>$b->phone]))->assertUnprocessable();$this->putJson('/api/account',$this->profile($u,['current_password'=>'wrong']))->assertUnprocessable();
  $this->putJson('/api/account',$this->profile($u,['role'=>'owner','status'=>'approved','opening_balance'=>1000000,'id'=>$b->id,'password'=>'hack']))->assertOk()->assertJsonPath('name','အမည်အသစ်')->assertJsonPath('agent_version',1)->assertJsonMissingPath('password');
  $this->assertSame('agent',$u->refresh()->role);$this->assertSame(0,$u->opening_balance);$this->assertTrue(Hash::check('TestingPassword!',$u->password));$this->assertSame('စမ်းသပ်သူ',$b->refresh()->name);$this->assertDatabaseHas('agent_changes',['agent_id'=>$u->id,'actor_id'=>$u->id]);
  $this->putJson('/api/account',$this->profile($u,['agent_version'=>0]))->assertConflict();
  $this->postJson('/api/logout');$this->postJson('/api/login',['phone'=>'09987654321','password'=>'TestingPassword!'])->assertOk();
 }
 public function test_owner_can_edit_own_account_without_creating_an_agent_audit_row():void{
  $u=$this->user('owner');$this->actingAs($u)->putJson('/api/account',$this->profile($u))->assertOk()->assertJsonPath('role','owner');$this->assertDatabaseCount('agent_changes',0);
 }
 public function test_guest_and_unapproved_accounts_cannot_edit_accounts_or_reset_passwords():void{
  $u=$this->user();$this->putJson('/api/account',$this->profile($u))->assertUnauthorized();$this->putJson('/api/agents/'.$u->id.'/password',$this->reset($u))->assertUnauthorized();
  $pending=$this->user(status:'pending');$this->actingAs($pending)->putJson('/api/account',$this->profile($pending))->assertForbidden();$this->putJson('/api/agents/'.$u->id.'/password',$this->reset($u))->assertForbidden();
 }
 public function test_only_owner_with_current_password_can_reset_an_agent_and_secrets_are_never_audited():void{
  $o=$this->user('owner');$u=$this->user();$token='old-remember-token';$u->forceFill(['remember_token'=>$token,'opening_balance'=>20000])->save();
  WebPushSubscription::create(['user_id'=>$u->id,'endpoint_hash'=>hash('sha256','https://fcm.googleapis.com/test'),'endpoint'=>'https://fcm.googleapis.com/test','public_key'=>'key','auth_token'=>'auth']);
  $this->actingAs($u)->putJson('/api/agents/'.$u->id.'/password',$this->reset($u))->assertForbidden();
  $this->actingAs($o)->withSession(['password_hash_web'=>$o->password])->putJson('/api/agents/'.$u->id.'/password',$this->reset($u,['current_password'=>'wrong']))->assertUnprocessable();
  $this->putJson('/api/agents/'.$u->id.'/password',$this->reset($u,['password_confirmation'=>'does-not-match']))->assertUnprocessable();
  $this->putJson('/api/agents/'.$o->id.'/password',$this->reset($o))->assertNotFound();
  $this->putJson('/api/agents/'.$u->id.'/password',$this->reset($u))->assertOk();
  $this->assertTrue(Hash::check('NewAgentPassword2026!',$u->refresh()->password));$this->assertNotSame($token,$u->remember_token);$this->assertSame(20000,$u->opening_balance);$this->assertSame('approved',$u->status);$this->assertDatabaseCount('web_push_subscriptions',0);
  $row=DB::table('agent_changes')->first();$this->assertSame($o->id,$row->actor_id);$this->assertTrue(json_decode($row->after,true)['password_reset']);$this->assertStringNotContainsString('Password',json_encode($row));
  $this->putJson('/api/agents/'.$u->id.'/password',$this->reset($u,['agent_version'=>0]))->assertConflict();
 }
 public function test_only_owner_can_initialize_missing_push_keys_and_repeat_setup_does_not_rotate_them():void{
  $o=$this->user('owner');$u=$this->user();$this->actingAs($u)->getJson('/api/push/settings')->assertJsonPath('configured',false)->assertJsonPath('can_setup',false);$this->postJson('/api/push/setup')->assertForbidden();
  $this->actingAs($o)->withSession(['password_hash_web'=>$o->password])->getJson('/api/push/settings')->assertJsonPath('can_setup',true);$first=$this->postJson('/api/push/setup')->assertOk()->assertJsonPath('configured',true)->assertJsonMissingPath('private_key')->json();
  $encrypted=Storage::disk('local')->get(PushKeys::FILE);$this->assertStringNotContainsString(config('webpush.private_key'),$encrypted);$this->assertStringNotContainsString($first['public_key'],$encrypted);
  $this->postJson('/api/push/setup')->assertOk()->assertJsonPath('public_key',$first['public_key']);
  config(['webpush.public_key'=>null,'webpush.private_key'=>null]);$this->assertTrue((new PushKeys)->configured());$this->assertSame($first['public_key'],config('webpush.public_key'));
 }
 public function test_existing_environment_keys_are_preserved_and_invalid_or_partial_keys_are_not_overwritten():void{
  $o=$this->user('owner');$key=VAPID::createVapidKeys();config(['webpush.public_key'=>$key['publicKey'],'webpush.private_key'=>$key['privateKey']]);$this->actingAs($o)->postJson('/api/push/setup')->assertOk()->assertJsonPath('public_key',$key['publicKey']);Storage::disk('local')->assertMissing(PushKeys::FILE);
  config(['webpush.private_key'=>null]);$this->postJson('/api/push/setup')->assertStatus(503);Storage::disk('local')->assertMissing(PushKeys::FILE);$this->assertSame($key['publicKey'],config('webpush.public_key'));
 }
 public function test_corrupt_saved_keys_fail_safely_instead_of_rotating_installed_devices():void{
  Storage::disk('local')->put(PushKeys::FILE,'corrupt');$o=$this->user('owner');$this->actingAs($o)->postJson('/api/push/setup')->assertStatus(503);$this->assertSame('corrupt',Storage::disk('local')->get(PushKeys::FILE));$this->getJson('/api/push/settings')->assertJsonPath('configured',false);
 }
 public function test_uploaded_image_revision_changes_and_unchanged_image_can_be_kept_during_edit():void{
  $o=$this->user('owner');$image='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jBz0AAAAASUVORK5CYII=';
  $body=['name'=>'ဓာတ်ပုံစမ်းသပ်','description'=>'အသေးစိတ်','price'=>1000,'commission'=>100,'stock'=>10,'image'=>$image,'active'=>true];
  $id=$this->actingAs($o)->postJson('/api/products',$body)->assertOk()->json('id');$path=DB::table('products')->value('image');$url=$this->getJson('/api/state?view=products')->json('products.0.image');$this->assertSame('/api/products/'.$id.'/image?v=0',$url);
  $this->putJson('/api/products/'.$id,[...$body,'image'=>$url,'lock_version'=>0,'name'=>'အမည်ပြင်ပြီး'])->assertOk();$this->assertSame($path,DB::table('products')->value('image'));$next=$this->getJson('/api/state?view=products')->json('products.0.image');$this->assertNotSame($url,$next);
  $this->get($next)->assertOk()->assertHeader('Content-Type','image/png');$this->postJson('/api/products',[...$body,'image'=>''])->assertUnprocessable();
 }
}
