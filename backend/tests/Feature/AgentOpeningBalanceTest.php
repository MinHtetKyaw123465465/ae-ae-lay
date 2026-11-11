<?php
namespace Tests\Feature;
use App\Models\{User,WebPushSubscription};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Queue};
use Illuminate\Support\Str;
use Tests\TestCase;
class AgentOpeningBalanceTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void{parent::setUp();Queue::fake();}
 private function user(string $role='agent',string $status='approved',int $quantity=0,int $balance=0):User{
  $u=User::create(['name'=>'စမ်းသပ်သူ','phone'=>'09'.random_int(100000000,999999999),'password'=>'TestingPassword!']);
  $u->forceFill(['role'=>$role,'status'=>$status,'opening_quantity'=>$quantity,'opening_balance'=>$balance,'opening_date'=>$quantity||$balance?now()->toDateString():null])->save();return $u->refresh();
 }
 private function payload(User $u,array $changes=[]):array{return array_replace(['name'=>$u->name,'phone'=>$u->phone,'status'=>$u->status,'opening_quantity'=>$u->opening_quantity,'opening_balance'=>$u->opening_balance,'opening_date'=>$u->opening_date,'agent_version'=>$u->agent_version,'note'=>'စာရင်းစတင်ထည့်သွင်းခြင်း'],$changes);}
 private function order(User $u,int $quantity=2,string $status='sent',?string $date=null):int{
  $p=DB::table('products')->insertGetId(['name'=>'ပစ္စည်း','description'=>'အသေးစိတ်','price'=>35000,'commission'=>500,'stock'=>1000,'image'=>'/products/cream.jpg','active'=>true,'created_at'=>now(),'updated_at'=>now()]);
  return DB::table('orders')->insertGetId(['agent_id'=>$u->id,'product_id'=>$p,'customer'=>'ဝယ်သူ','phone'=>'09900000000','address'=>'ရန်ကုန်','quantity'=>$quantity,'unit_price'=>35000,'unit_commission'=>500,'status'=>$status,'created_at'=>$date??now(),'updated_at'=>now(),'sent_at'=>$status==='sent'?($date??now()):null]);
 }
 public function test_owner_sees_total_counts_and_all_statuses_with_search_and_pagination():void{
  $o=$this->user('owner');for($i=0;$i<13;$i++)$this->user();$p=$this->user(status:'pending');$this->user(status:'rejected');
  $this->actingAs($o)->getJson('/api/state?view=agents&agent_status=all')->assertOk()->assertJsonPath('counts.agents.all',15)->assertJsonPath('counts.agents.approved',13)->assertJsonPath('counts.agents.pending',1)->assertJsonPath('counts.agents.rejected',1)->assertJsonCount(10,'users')->assertJsonPath('pagination.users.total',15);
  $this->getJson('/api/state?view=agents&agent_status=all&users_page=2')->assertJsonCount(5,'users');
  $this->getJson('/api/state?view=agents&agent_status=all&search='.$p->phone)->assertJsonPath('pagination.users.total',1)->assertJsonPath('counts.agents.all',15)->assertJsonPath('users.0.id',$p->id);
  $this->getJson('/api/state?view=dashboard')->assertJsonPath('counts.agents.all',15);
 }
 public function test_guest_and_agents_cannot_read_edit_or_view_another_agents_history():void{
  $u=$this->user();$o=$this->user('owner');$this->getJson('/api/agents/'.$u->id)->assertUnauthorized();
  $this->actingAs($u)->getJson('/api/agents/'.$u->id)->assertForbidden();$this->getJson('/api/agents/'.$u->id.'/history')->assertForbidden();$this->putJson('/api/agents/'.$u->id,$this->payload($u))->assertForbidden();$this->patchJson('/api/agents/'.$u->id,['status'=>'rejected'])->assertForbidden();
  $this->actingAs($o)->withSession(['password_hash_web'=>$o->password])->getJson('/api/agents/'.$o->id)->assertNotFound();$this->putJson('/api/agents/'.$o->id,$this->payload($o))->assertNotFound();
 }
 public function test_owner_edits_profile_and_imports_opening_balance_without_creating_orders():void{
  $o=$this->user('owner');$u=$this->user();$this->actingAs($o)->putJson('/api/agents/'.$u->id,$this->payload($u,['name'=>'အမည်အသစ်','phone'=>'09987654321','opening_quantity'=>100,'opening_balance'=>20000,'opening_date'=>now()->toDateString(),'role'=>'owner','password'=>'hacked']))->assertOk()->assertJsonPath('agent_version',1);
  $u->refresh();$this->assertSame('agent',$u->role);$this->assertTrue(\Illuminate\Support\Facades\Hash::check('TestingPassword!',$u->password));$this->assertDatabaseCount('orders',0);$this->assertDatabaseCount('payouts',0);
  $row=DB::table('agent_changes')->first();$this->assertSame($o->id,$row->actor_id);$this->assertSame(0,json_decode($row->before,true)['opening_balance']);$this->assertSame(20000,json_decode($row->after,true)['opening_balance']);
  $this->getJson('/api/agents/'.$u->id)->assertJsonPath('sold',100)->assertJsonPath('balance',20000)->assertJsonMissingPath('password');
  $this->postJson('/api/logout')->assertOk();$this->postJson('/api/login',['phone'=>'09987654321','password'=>'TestingPassword!'])->assertOk();
 }
 public function test_opening_sales_affect_all_time_rankings_but_not_date_ranges():void{
  $o=$this->user('owner');$a=$this->user(quantity:100);$b=$this->user();$this->user(status:'pending',quantity:10000);$this->order($a,2,'sent','2026-11-10 08:00:00');$this->order($a,50,'sent','2026-10-01 08:00:00');$this->order($a,999,'pending');$this->order($a,999,'cancelled');$this->order($b,3,'sent','2026-11-10 08:00:00');
  $this->actingAs($o)->getJson('/api/rankings')->assertOk()->assertJsonPath('data.0.id',$a->id)->assertJsonPath('data.0.total',152)->assertJsonCount(2,'data');
  $this->getJson('/api/rankings?from=2026-11-01&to=2026-11-30')->assertJsonPath('data.0.id',$b->id)->assertJsonPath('data.0.total',3)->assertJsonPath('data.1.total',2);
  $this->getJson('/api/rankings?to=2026-09-01')->assertJsonPath('data.0.total',0);
  $this->getJson('/api/rankings?from=2026-11-01')->assertJsonPath('data.0.total',3);
 }
 public function test_opening_commission_is_payable_and_cannot_be_paid_twice_or_overpaid():void{
  $o=$this->user('owner');$u=$this->user(quantity:100,balance:20000);$this->order($u);$this->actingAs($o)->getJson('/api/state?view=earnings')->assertJsonPath('summary.opening_balance',20000)->assertJsonPath('summary.earned',21000)->assertJsonPath('summary.balance',21000);
  $body=['agent_id'=>$u->id,'amount'=>21000,'reference'=>'ငွေသား','request_key'=>(string)Str::uuid()];$this->postJson('/api/payouts',$body)->assertCreated();$this->postJson('/api/payouts',$body)->assertOk();$this->assertDatabaseCount('payouts',1);$this->postJson('/api/payouts',[...$body,'request_key'=>(string)Str::uuid(),'amount'=>1])->assertUnprocessable();$this->getJson('/api/agents/'.$u->id)->assertJsonPath('balance',0);
  $this->putJson('/api/agents/'.$u->id,$this->payload($u,['opening_balance'=>19000]))->assertUnprocessable()->assertJsonValidationErrors('opening_balance');$this->assertDatabaseCount('agent_changes',0);$this->assertSame(20000,$u->refresh()->opening_balance);
 }
 public function test_opening_commission_totals_remain_scoped_to_each_agent():void{
  $a=$this->user(balance:20000);$b=$this->user(balance:90000);$this->order($a);DB::table('payouts')->insert(['agent_id'=>$a->id,'amount'=>5000,'reference'=>'ငွေသား','created_at'=>now(),'updated_at'=>now()]);
  $this->actingAs($a)->getJson('/api/state?view=earnings')->assertJsonPath('summary.opening_balance',20000)->assertJsonPath('summary.earned',21000)->assertJsonPath('summary.paid',5000)->assertJsonPath('summary.balance',16000)->assertJsonCount(0,'users');
 }
 public function test_opening_fields_require_valid_dates_whole_nonnegative_values_and_reason():void{
  $o=$this->user('owner');$u=$this->user();$other=$this->user();$this->actingAs($o);
  foreach([['opening_quantity'=>-1],['opening_balance'=>-1],['opening_quantity'=>1.5],['opening_balance'=>1.5],['opening_quantity'=>1000000001],['opening_balance'=>1000000000001],['opening_quantity'=>1,'opening_date'=>null],['opening_quantity'=>1,'opening_date'=>now()->addDay()->toDateString()],['opening_quantity'=>1,'opening_date'=>now()->toDateString(),'note'=>'  '],['phone'=>$other->phone],['name'=>''],['status'=>'owner']] as $changes)$this->putJson('/api/agents/'.$u->id,$this->payload($u,$changes))->assertUnprocessable();
  $this->assertDatabaseCount('agent_changes',0);$this->assertSame(0,$u->refresh()->opening_quantity);
 }
 public function test_stale_edits_are_rejected_and_identical_saves_do_not_duplicate_history():void{
  $o=$this->user('owner');$u=$this->user();$first=$this->payload($u,['opening_quantity'=>50,'opening_date'=>now()->toDateString()]);$this->actingAs($o)->putJson('/api/agents/'.$u->id,$first)->assertOk();
  $this->putJson('/api/agents/'.$u->id,[...$first,'name'=>'stale edit'])->assertConflict();$this->patchJson('/api/agents/'.$u->id,['status'=>'rejected','agent_version'=>0])->assertConflict();
  $this->putJson('/api/agents/'.$u->id,$this->payload($u->refresh()))->assertOk()->assertJsonPath('agent_version',1);$this->assertDatabaseCount('agent_changes',1);
  $body=$this->payload($u);unset($body['agent_version']);$this->putJson('/api/agents/'.$u->id,$body)->assertUnprocessable();
 }
 public function test_change_history_is_paginated_scoped_and_records_status_actions():void{
  $o=$this->user('owner');$a=$this->user();$b=$this->user();$this->actingAs($o);
  for($i=1;$i<=13;$i++){$this->putJson('/api/agents/'.$a->id,$this->payload($a->refresh(),['name'=>'အမည် '.$i]))->assertOk();}
  $this->patchJson('/api/agents/'.$b->id,['status'=>'rejected'])->assertOk();$this->assertDatabaseHas('agent_changes',['agent_id'=>$b->id,'actor_id'=>$o->id]);
  $this->getJson('/api/agents/'.$a->id.'/history')->assertJsonCount(10,'data')->assertJsonPath('meta.total',13)->assertJsonPath('data.0.after.name','အမည် 13')->assertJsonPath('data.0.actor_name',$o->name);
  $this->getJson('/api/agents/'.$a->id.'/history?page=2')->assertJsonCount(3,'data');$this->getJson('/api/agents/'.$a->id.'/history?page=999')->assertJsonPath('meta.current_page',2);$this->getJson('/api/agents/'.$b->id.'/history')->assertJsonCount(1,'data');
 }
 public function test_suspension_revokes_access_preserves_money_and_can_be_reversed():void{
  $o=$this->user('owner');$u=$this->user(balance:20000);$this->actingAs($o)->putJson('/api/agents/'.$u->id,$this->payload($u,['status'=>'rejected']))->assertOk();
  $this->getJson('/api/rankings')->assertJsonCount(0,'data');$this->getJson('/api/state?view=earnings')->assertJsonPath('summary.balance',20000);
  $this->postJson('/api/logout');$this->postJson('/api/login',['phone'=>$u->phone,'password'=>'TestingPassword!'])->assertForbidden();$this->actingAs($o)->withSession(['password_hash_web'=>$o->password])->patchJson('/api/agents/'.$u->id,['status'=>'approved'])->assertOk();$this->getJson('/api/rankings')->assertJsonCount(1,'data');$this->assertDatabaseCount('agent_changes',2);
 }
}
