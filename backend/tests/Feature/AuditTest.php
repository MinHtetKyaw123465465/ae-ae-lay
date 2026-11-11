<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Hash,Storage};
use Illuminate\Support\Str;
class AuditTest extends TestCase {
 use RefreshDatabase;
 private function user(string $role='agent',string $status='approved'):User{$u=User::create(['name'=>'အကောင့် '.Str::random(8),'phone'=>'09'.random_int(100000000,999999999),'password'=>'TestingPassword!']);$u->forceFill(compact('role','status'))->save();return $u;}
 private function product(int $stock=100):int{return DB::table('products')->insertGetId(['name'=>'ပစ္စည်း','description'=>'အသေးစိတ်','price'=>35000,'commission'=>5000,'stock'=>$stock,'image'=>'/products/cream.jpg','active'=>true,'created_at'=>now(),'updated_at'=>now()]);}
 private function order(User $a,int $p,string $status='sent',int $quantity=2,array $extra=[]):int{return DB::table('orders')->insertGetId(['agent_id'=>$a->id,'product_id'=>$p,'customer'=>'ဝယ်သူ','phone'=>'09123456789','address'=>'ရန်ကုန်','quantity'=>$quantity,'unit_price'=>35000,'unit_commission'=>5000,'status'=>$status,'sent_at'=>$status==='sent'?now():null,'created_at'=>now(),'updated_at'=>now(),...$extra]);}
 private function productBody():array{return ['lock_version'=>0,'name'=>'ပစ္စည်း','description'=>'အသေးစိတ်','price'=>35000,'commission'=>5000,'stock'=>100,'image'=>'/products/cream.jpg','active'=>true];}
 public function test_order_pages_are_bounded_stable_and_totals_cover_every_page():void {
  $owner=$this->user('owner');$a=$this->user();$p=$this->product();for($i=0;$i<37;$i++)$this->order($a,$p);
  $first=$this->actingAs($owner)->getJson('/api/state?view=orders')->assertOk()->json();$this->assertCount(10,$first['orders']);$this->assertSame(37,$first['pagination']['orders']['total']);$this->assertSame(4,$first['pagination']['orders']['last_page']);$this->assertSame(37,$first['counts']['orders']['sent']);
  $next=$this->getJson('/api/state?view=orders&orders_page=2')->json();$this->assertEmpty(array_intersect(array_column($first['orders'],'id'),array_column($next['orders'],'id')));
  $overview=$this->getJson('/api/state?view=dashboard')->json();$this->assertCount(5,$overview['orders']);$this->assertSame(37,$overview['summary']['sent_orders']);$this->assertSame(370000,$overview['summary']['commission']);
  $last=$this->getJson('/api/state?view=orders&orders_page=999')->json();$this->assertCount(7,$last['orders']);$this->assertSame(4,$last['pagination']['orders']['current_page']);
  $this->getJson('/api/state?view=orders&per_page=100000')->assertUnprocessable();$this->getJson('/api/state?orders_page=-1')->assertUnprocessable();
 }
 public function test_all_growing_lists_and_form_options_are_paginated():void {
  $owner=$this->user('owner');$p=$this->product();for($i=0;$i<24;$i++){$a=$this->user();$this->order($a,$p);$this->product();DB::table('payouts')->insert(['agent_id'=>$a->id,'amount'=>1000,'reference'=>'မှတ်တမ်း','created_at'=>now(),'updated_at'=>now()]);}
  $this->actingAs($owner);foreach(['agents'=>'users','products'=>'products','earnings'=>'payouts'] as $view=>$key){$state=$this->getJson('/api/state?view='.$view)->assertOk()->json();$this->assertCount(10,$state[$key]);$this->assertGreaterThan(10,$state['pagination'][$key]['total']);}
  $earn=$this->getJson('/api/state?view=earnings&users_page=2&payouts_page=2')->json();$this->assertSame(240000,$earn['summary']['earned']);$this->assertSame(24000,$earn['summary']['paid']);$this->assertSame(216000,$earn['summary']['balance']);$this->assertSame(9000,$earn['users'][0]['balance']);
  $rank=$this->getJson('/api/rankings?rankings_page=2')->json();$this->assertCount(10,$rank['data']);$this->assertSame(24,$rank['meta']['total']);
  foreach(['agents','products'] as $type){$response=$this->getJson('/api/options/'.$type)->assertOk()->json();$this->assertCount(10,$response['data']);$this->assertGreaterThan(10,$response['meta']['total']);}
 }
 public function test_search_is_literal_and_applies_before_pagination():void {
  $owner=$this->user('owner');$a=$this->user();$p=$this->product();for($i=0;$i<15;$i++)$this->order($a,$p);$id=$this->order($a,$p,extra:['customer'=>'50%_အမည်']);
  $s=$this->actingAs($owner)->getJson('/api/state?view=orders&search='.urlencode('%_'))->assertOk()->json();$this->assertSame(1,$s['pagination']['orders']['total']);$this->assertSame($id,$s['orders'][0]['id']);$this->getJson('/api/state?search='.str_repeat('a',101))->assertUnprocessable();
 }
 public function test_agents_cannot_browse_others_by_page_detail_export_or_options():void {
  $a=$this->user();$b=$this->user();$p=$this->product();$other=$this->order($b,$p,extra:['customer'=>'PRIVATE-CUSTOMER']);for($i=0;$i<15;$i++)$this->order($a,$p);
  $this->actingAs($a)->getJson('/api/state?view=orders&orders_page=2')->assertOk()->assertDontSee('PRIVATE-CUSTOMER');$this->getJson('/api/orders/'.$other)->assertNotFound();$this->getJson('/api/state?view=agents')->assertForbidden();$this->getJson('/api/options/agents')->assertForbidden();$csv=$this->get('/api/orders/export')->assertOk()->streamedContent();$this->assertStringNotContainsString('PRIVATE-CUSTOMER',$csv);$rank=$this->getJson('/api/rankings')->json('data');foreach($rank as $row)$this->assertSame(['id','name','total'],array_keys($row));
 }
 public function test_csv_streams_all_filtered_rows_and_escapes_formula_cells():void {
  $owner=$this->user('owner');$a=$this->user();$p=$this->product();for($i=0;$i<24;$i++)$this->order($a,$p,'pending',1,['customer'=>$i===0?' =1+1':'普通']);$this->order($a,$p,'sent');
  $response=$this->actingAs($owner)->get('/api/orders/export?status=pending&per_page=10')->assertOk();$csv=$response->streamedContent();$this->assertStringStartsWith("\xEF\xBB\xBF",$csv);$this->assertCount(25,array_filter(explode("\n",trim($csv))));$this->assertStringContainsString("' =1+1",$csv);
 }
 public function test_stock_edits_cannot_overwrite_orders_placed_after_form_was_opened():void {
  $owner=$this->user('owner');$a=$this->user();$p=$this->product();$this->actingAs($owner)->postJson('/api/orders',['agent_id'=>$a->id,'product_id'=>$p,'customer'=>'ဝယ်သူ','phone'=>'09123456789','address'=>'ရန်ကုန်','quantity'=>2])->assertCreated();$this->putJson('/api/products/'.$p,$this->productBody())->assertConflict();$this->assertSame(98,DB::table('products')->where('id',$p)->value('stock'));$this->putJson('/api/products/'.$p,[...$this->productBody(),'stock'=>98,'lock_version'=>1])->assertOk();
 }
 public function test_mobile_retries_do_not_duplicate_orders_or_stock_reservations():void {
  $a=$this->user();$p=$this->product();$body=['request_key'=>(string)Str::uuid(),'product_id'=>$p,'customer'=>'ဝယ်သူ','phone'=>'09123456789','address'=>'ရန်ကုန်','quantity'=>2];$first=$this->actingAs($a)->postJson('/api/orders',$body)->assertCreated()->json('id');$again=$this->postJson('/api/orders',$body)->assertOk()->json('id');$this->assertSame($first,$again);$this->assertDatabaseCount('orders',1);$this->assertSame(98,DB::table('products')->value('stock'));$this->postJson('/api/orders',[...$body,'quantity'=>3])->assertConflict();
 }
 public function test_mobile_retries_do_not_duplicate_payouts():void {
  $owner=$this->user('owner');$a=$this->user();$p=$this->product();$this->order($a,$p);$body=['request_key'=>(string)Str::uuid(),'agent_id'=>$a->id,'amount'=>6000,'reference'=>'မှတ်တမ်း'];$first=$this->actingAs($owner)->postJson('/api/payouts',$body)->assertCreated()->json('id');$this->assertSame($first,$this->postJson('/api/payouts',$body)->assertOk()->json('id'));$this->assertDatabaseCount('payouts',1);$this->postJson('/api/payouts',[...$body,'amount'=>6001])->assertConflict();$this->postJson('/api/payouts',['agent_id'=>$a->id,'amount'=>4001,'reference'=>'နောက်ထပ်'])->assertUnprocessable();
 }
 public function test_uploaded_images_are_private_files_and_not_embedded_in_list_payloads():void {
  Storage::fake('local');$owner=$this->user('owner');$image='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jBz0AAAAASUVORK5CYII=';
  $id=$this->actingAs($owner)->postJson('/api/products',[...$this->productBody(),'image'=>$image])->assertOk()->json('id');$stored=DB::table('products')->where('id',$id)->value('image');$this->assertStringStartsWith('shop-images/',$stored);Storage::disk('local')->assertExists($stored);$list=$this->getJson('/api/state?view=products')->json();$this->assertSame('/api/products/'.$id.'/image?v=0',$list['products'][0]['image']);$this->get('/api/products/'.$id.'/image')->assertOk()->assertHeader('X-Content-Type-Options','nosniff');$this->postJson('/api/logout');$this->get('/api/products/'.$id.'/image')->assertUnauthorized();
 }
 public function test_password_change_requires_current_password_and_updates_the_hash():void {
  $a=$this->user();$body=['current_password'=>'wrong','password'=>'NewTestingPassword!','password_confirmation'=>'NewTestingPassword!'];$this->actingAs($a)->putJson('/api/password',$body)->assertUnprocessable();$this->putJson('/api/password',[...$body,'current_password'=>'TestingPassword!'])->assertOk();$this->assertTrue(Hash::check('NewTestingPassword!',$a->fresh()->password));
 }
 public function test_bounded_payload_and_query_count_with_five_thousand_orders():void {
  $owner=$this->user('owner');$a=$this->user();$p=$this->product();$row=['agent_id'=>$a->id,'product_id'=>$p,'customer'=>'ဝယ်သူ','phone'=>'09123456789','address'=>'ရန်ကုန်','quantity'=>1,'unit_price'=>35000,'unit_commission'=>5000,'status'=>'sent','sent_at'=>now(),'created_at'=>now(),'updated_at'=>now()];for($i=0;$i<10;$i++)DB::table('orders')->insert(array_fill(0,500,$row));
  DB::enableQueryLog();$response=$this->actingAs($owner)->getJson('/api/state?view=dashboard')->assertOk();$queries=count(DB::getQueryLog());DB::disableQueryLog();$this->assertLessThan(20,$queries);$this->assertLessThan(20000,strlen($response->getContent()));$this->assertCount(5,$response->json('orders'));$this->assertSame(5000,$response->json('summary.sent_orders'));$this->assertSame(25000000,$response->json('summary.commission'));
 }
 public function test_private_pages_have_no_store_and_browser_security_headers():void {
  $this->get('/api/state')->assertUnauthorized()->assertHeader('X-Frame-Options','DENY');$this->assertStringContainsString('no-store',$this->get('/api/state')->headers->get('Cache-Control'));
  $this->actingAs($this->user())->getJson('/api/state')->assertOk()->assertHeader('X-Frame-Options','DENY')->assertHeader('X-Content-Type-Options','nosniff');$this->assertStringContainsString('no-store',$this->getJson('/api/state')->headers->get('Cache-Control'));$this->assertStringContainsString("frame-ancestors 'none'",$this->getJson('/api/state')->headers->get('Content-Security-Policy'));
 }

 public function test_malformed_identifiers_and_login_inputs_do_not_cause_server_errors():void {
  $this->postJson('/api/login',['phone'=>['bad'],'password'=>'TestingPassword!'])->assertUnprocessable();$this->actingAs($this->user('owner'));$this->getJson('/api/orders/not-a-number')->assertNotFound();$this->patchJson('/api/orders/not-a-number/status',['status'=>'sent'])->assertNotFound();$this->putJson('/api/products/not-a-number',[])->assertNotFound();$this->getJson('/api/state?search[]=bad')->assertUnprocessable();
 }

 public function test_order_price_changes_require_review_instead_of_silently_charging_a_new_price():void {
  $a=$this->user();$p=$this->product();$body=['product_id'=>$p,'customer'=>'ဝယ်သူ','phone'=>'09123456789','address'=>'ရန်ကုန်','quantity'=>1,'expected_price'=>30000,'expected_commission'=>5000];$this->actingAs($a)->postJson('/api/orders',$body)->assertConflict();$this->assertDatabaseCount('orders',0);$this->assertSame(100,DB::table('products')->value('stock'));$this->postJson('/api/orders',[...$body,'expected_price'=>35000])->assertCreated();
 }
 public function test_cache_maintenance_preserves_live_entries_and_business_records():void {
  config(['cache.default'=>'database']);$a=$this->user();$p=$this->product();$this->order($a,$p);DB::table('cache')->insert([['key'=>'audit-expired','value'=>'s:3:"old";','expiration'=>time()-100],['key'=>'audit-live','value'=>'s:3:"new";','expiration'=>time()+100]]);$this->artisan('app:prune-expired-cache')->assertSuccessful();$this->assertDatabaseMissing('cache',['key'=>'audit-expired']);$this->assertDatabaseHas('cache',['key'=>'audit-live']);$this->assertDatabaseCount('orders',1);$this->assertDatabaseCount('users',1);
 }

 public function test_passwords_are_not_silently_truncated_or_rejected_as_server_errors():void {
  foreach([str_repeat('x',73),str_repeat('က',25),"validpassword\0suffix"] as $password)$this->postJson('/api/register',['name'=>'စမ်းသပ်သူ','phone'=>'09911223344','password'=>$password])->assertUnprocessable();
  $this->assertDatabaseCount('users',0);
 }
}
