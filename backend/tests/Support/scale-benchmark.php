<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(config('database.connections.'.config('database.default').'.database')!=='aeae_testing')throw new RuntimeException('Benchmark must use the isolated aeae_testing database.');
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Services\ShopQueries;
$owner=App\Models\User::where('role','owner')->firstOrFail();$agent=App\Models\User::where('role','agent')->where('status','approved')->firstOrFail();$product=DB::table('products')->first();
$marker='AUDIT-SCALE-'.bin2hex(random_bytes(4));
$row=['agent_id'=>$agent->id,'product_id'=>$product->id,'customer'=>$marker,'phone'=>'09900000000','address'=>'ရန်ကုန်','quantity'=>1,'unit_price'=>35000,'unit_commission'=>5000,'status'=>'sent','sent_at'=>now(),'created_at'=>now(),'updated_at'=>now()];
try{
 for($i=0;$i<100;$i++)DB::table('orders')->insert(array_fill(0,500,$row));
 $service=$app->make(ShopQueries::class);$results=[];
 foreach(['dashboard'=>['view'=>'dashboard','status'=>'pending'],'orders_first'=>['view'=>'orders','per_page'=>10],'orders_deep'=>['view'=>'orders','per_page'=>10,'orders_page'=>4000],'earnings'=>['view'=>'earnings','per_page'=>10]] as $name=>$query){
  $times=[];$payload='';$queryCount=0;
  for($i=0;$i<10;$i++){$request=Request::create('/api/state','GET',$query);$request->setUserResolver(fn()=>$owner);DB::enableQueryLog();DB::flushQueryLog();$start=hrtime(true);$result=$service->state($request);$times[]=(hrtime(true)-$start)/1e6;$payload=json_encode($result);$queryCount=count(DB::getQueryLog());DB::disableQueryLog();}
  sort($times);$results[$name]=['p50_ms'=>round($times[4],2),'max_ms'=>round(max($times),2),'json_bytes'=>strlen($payload),'sql_queries'=>$queryCount,'returned_rows'=>count($result['orders'])+count($result['payouts'])+count($result['users'])];
 }
 echo json_encode(['additional_orders'=>50000,'database'=>'MySQL','peak_php_mb'=>round(memory_get_peak_usage(true)/1048576,2),'results'=>$results],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
}finally{DB::table('orders')->where('customer',$marker)->delete();}
