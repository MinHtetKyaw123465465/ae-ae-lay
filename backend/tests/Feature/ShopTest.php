<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class ShopTest extends TestCase {
 use RefreshDatabase;
 private function user(string $role='agent',string $status='approved'):User{$u=User::create(['name'=>'စမ်းသပ်အကောင့်','phone'=>'09'.random_int(100000000,999999999),'password'=>'TestingPassword!']);$u->forceFill(compact('role','status'))->save();return $u;}
 private function product(int $stock=10):int{return DB::table('products')->insertGetId(['name'=>'စမ်းသပ်ပစ္စည်း','description'=>'အကြောင်းအရာ','price'=>35000,'commission'=>5000,'stock'=>$stock,'image'=>'/products/cream.jpg','active'=>true,'created_at'=>now(),'updated_at'=>now()]);}
 private function order(User $a,int $p,string $status='pending',int $q=2):int{return DB::table('orders')->insertGetId(['agent_id'=>$a->id,'product_id'=>$p,'customer'=>'ဝယ်သူအမည်','phone'=>'09123456789','address'=>'ရန်ကုန်','quantity'=>$q,'unit_price'=>35000,'unit_commission'=>5000,'status'=>$status,'sent_at'=>$status==='sent'?now():null,'created_at'=>now(),'updated_at'=>now()]);}
 public function test_registration_cannot_promote_itself_and_pending_login_is_blocked():void{$this->postJson('/api/register',['name'=>'မသဇင်','phone'=>'09123456789','password'=>'TestingPassword!','role'=>'owner','status'=>'approved'])->assertCreated();$u=User::first();$this->assertSame('agent',$u->role);$this->assertSame('pending',$u->status);$this->postJson('/api/login',['phone'=>$u->phone,'password'=>'TestingPassword!'])->assertForbidden();$this->getJson('/api/state')->assertUnauthorized();}
 public function test_approved_agent_can_login_and_session_is_revoked_on_logout():void{$a=$this->user();$this->postJson('/api/login',['phone'=>$a->phone,'password'=>'TestingPassword!'])->assertOk();$this->getJson('/api/state')->assertOk();$this->postJson('/api/logout')->assertOk();$this->getJson('/api/state')->assertUnauthorized();}
 public function test_agents_only_receive_their_own_customers_and_payouts_but_public_rankings():void{$a=$this->user();$b=$this->user();$p=$this->product();$own=$this->order($a,$p,'sent');$this->order($b,$p,'sent',5);DB::table('payouts')->insert(['agent_id'=>$b->id,'amount'=>1000,'reference'=>'သီးသန့်မှတ်တမ်း','created_at'=>now(),'updated_at'=>now()]);$state=$this->actingAs($a)->getJson('/api/state?view=orders')->assertOk()->json();$this->assertCount(1,$state['orders']);$this->assertSame($own,$state['orders'][0]['id']);$this->assertCount(0,$state['users']);$rank=$this->getJson('/api/rankings')->assertOk()->json('data');$this->assertCount(2,$rank);$this->assertSame(5,$rank[0]['total']);$this->assertCount(0,$this->getJson('/api/state?view=earnings')->json('payouts'));}
 public function test_server_enforces_agent_identity_and_snapshots_fixed_prices():void{$a=$this->user();$b=$this->user();$p=$this->product();$this->actingAs($a)->postJson('/api/orders',['agent_id'=>$b->id,'product_id'=>$p,'customer'=>'မသီတာ','phone'=>'09123456789','address'=>'ရန်ကုန်','quantity'=>3,'unit_price'=>1,'unit_commission'=>999999])->assertCreated();$o=DB::table('orders')->first();$this->assertSame($a->id,$o->agent_id);$this->assertSame(35000,$o->unit_price);$this->assertSame(5000,$o->unit_commission);$this->assertSame(7,DB::table('products')->value('stock'));$this->assertSame('pending',$o->status);}
 public function test_out_of_stock_order_does_not_write_or_change_stock():void{$a=$this->user();$p=$this->product(1);$this->actingAs($a)->postJson('/api/orders',['product_id'=>$p,'customer'=>'မသီတာ','phone'=>'09123456789','address'=>'ရန်ကုန်','quantity'=>2])->assertUnprocessable();$this->assertDatabaseCount('orders',0);$this->assertSame(1,DB::table('products')->value('stock'));}
 public function test_only_owner_can_change_status_manage_products_approve_or_pay():void{$a=$this->user();$p=$this->product();$o=$this->order($a,$p);$this->actingAs($a)->patchJson("/api/orders/$o/status",['status'=>'sent'])->assertForbidden();$this->postJson('/api/products',[])->assertForbidden();$this->patchJson("/api/agents/{$a->id}",['status'=>'approved'])->assertForbidden();$this->postJson('/api/payouts',[])->assertForbidden();}
 public function test_cancellation_restores_stock_only_once_and_cannot_be_sent():void{$owner=$this->user('owner');$a=$this->user();$p=$this->product(8);$o=$this->order($a,$p);$this->actingAs($owner)->patchJson("/api/orders/$o/status",['status'=>'cancelled'])->assertOk();$this->patchJson("/api/orders/$o/status",['status'=>'cancelled'])->assertOk();$this->assertSame(10,DB::table('products')->value('stock'));$this->patchJson("/api/orders/$o/status",['status'=>'sent'])->assertUnprocessable();}
 public function test_commission_is_only_payable_after_sending_and_overpayment_is_rejected():void{$owner=$this->user('owner');$a=$this->user();$p=$this->product();$o=$this->order($a,$p);$pay=['agent_id'=>$a->id,'amount'=>10000,'reference'=>'ငွေရှင်းမှတ်တမ်း'];$this->actingAs($owner)->postJson('/api/payouts',$pay)->assertUnprocessable();$this->patchJson("/api/orders/$o/status",['status'=>'sent'])->assertOk();$this->postJson('/api/payouts',[...$pay,'amount'=>10001])->assertUnprocessable();$this->postJson('/api/payouts',$pay)->assertCreated();$this->postJson('/api/payouts',[...$pay,'amount'=>1])->assertUnprocessable();$this->assertDatabaseCount('payouts',1);}
 public function test_product_edit_does_not_recalculate_historical_commission():void{$owner=$this->user('owner');$a=$this->user();$p=$this->product();$o=$this->order($a,$p,'sent');$this->actingAs($owner)->putJson("/api/products/$p",['lock_version'=>0,'name'=>'ဈေးပြောင်းပစ္စည်း','description'=>'အကြောင်းအရာ','price'=>40000,'commission'=>7000,'stock'=>10,'image'=>'/products/cream.jpg','active'=>true])->assertOk();$this->assertSame(5000,DB::table('orders')->where('id',$o)->value('unit_commission'));$this->postJson('/api/payouts',['agent_id'=>$a->id,'amount'=>14000,'reference'=>'မှတ်ချက်'])->assertUnprocessable();}
 public function test_owner_approval_allows_pending_agent_to_login():void{$owner=$this->user('owner');$a=$this->user('agent','pending');$this->actingAs($owner)->patchJson("/api/agents/{$a->id}",['status'=>'approved'])->assertOk();$this->postJson('/api/logout');$this->postJson('/api/login',['phone'=>$a->phone,'password'=>'TestingPassword!'])->assertOk();}
 public function test_product_rejects_unsafe_image_and_commission_above_price():void{$owner=$this->user('owner');$this->actingAs($owner)->postJson('/api/products',['name'=>'ပစ္စည်း','description'=>'အကြောင်းအရာ','price'=>1000,'commission'=>1001,'stock'=>10,'image'=>'/products/cream.jpg','active'=>true])->assertUnprocessable();$this->postJson('/api/products',['name'=>'ပစ္စည်း','description'=>'အကြောင်းအရာ','price'=>1000,'commission'=>100,'stock'=>10,'image'=>'data:image/png;base64,c2NyaXB0','active'=>true])->assertUnprocessable();}

 public function test_custom_rankings_include_full_end_day_and_never_expose_customer_details():void {
  $a=$this->user();$b=$this->user();$p=$this->product();
  foreach([['2026-11-01 23:59:59',8],['2026-11-02 00:00:00',2],['2026-11-02 23:59:59',3],['2026-11-03 00:00:00',9]] as [$date,$quantity]){$id=$this->order($b,$p,'sent',$quantity);DB::table('orders')->where('id',$id)->update(['sent_at'=>$date]);}
  $result=$this->actingAs($a)->getJson('/api/rankings?from=2026-11-02&to=2026-11-02')->assertOk()->json('data');
  $this->assertSame(5,$result[0]['total']);$this->assertSame($b->id,$result[0]['id']);$this->assertSame(['id','name','total'],array_keys($result[0]));
  $all=$this->getJson('/api/rankings')->assertOk()->json('data');$this->assertSame(22,$all[0]['total']);
 }
 public function test_rankings_require_login_and_reject_reversed_or_malformed_dates():void {
  $this->getJson('/api/rankings')->assertUnauthorized();$a=$this->user();
  $this->actingAs($a)->getJson('/api/rankings?from=2026-11-03&to=2026-11-02')->assertUnprocessable();
  $this->getJson('/api/rankings?from=2026-02-30&to=2026-03-02')->assertUnprocessable();
  $this->getJson('/api/rankings?from=invalid')->assertUnprocessable();
 }
 public function test_guest_api_cannot_retrieve_any_shop_data():void {
  foreach(['/api/me','/api/state','/api/rankings'] as $route){$this->getJson($route)->assertUnauthorized();$this->get($route)->assertUnauthorized();}
 }

 public function test_login_attempts_do_not_consume_registration_rate_limit():void {
  $a=$this->user();for($i=0;$i<5;$i++){$this->postJson('/api/login',['phone'=>$a->phone,'password'=>'TestingPassword!'])->assertOk();$this->postJson('/api/logout')->assertOk();}
  $this->postJson('/api/register',['name'=>'မသီတာ','phone'=>'09912345678','password'=>'TestingPassword!'])->assertCreated();
 }
}
