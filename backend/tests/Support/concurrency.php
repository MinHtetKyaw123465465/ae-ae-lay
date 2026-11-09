<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(config('database.connections.'.config('database.default').'.database')!=='aeae_testing')throw new RuntimeException('Concurrency checks must use aeae_testing.');
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\User;
if(($argv[1]??'')==='worker'){
 [$action,$ownerId,$agentId,$productId,$barrier]=array_slice($argv,2);$request=Request::create('/api/'.$action,'POST',$action==='payouts'?['agent_id'=>(int)$agentId,'amount'=>8000,'reference'=>'parallel','request_key'=>(string)Illuminate\Support\Str::uuid()]:['agent_id'=>(int)$agentId,'product_id'=>(int)$productId,'quantity'=>1,'customer'=>'parallel','phone'=>'09900000000','address'=>'ရန်ကုန်','request_key'=>(string)Illuminate\Support\Str::uuid()]);$owner=User::findOrFail($ownerId);$request->setUserResolver(fn()=>$owner);
 file_put_contents($barrier.'.'.getmypid(),'ready');$deadline=microtime(true)+10;while(!file_exists($barrier)){if(microtime(true)>$deadline)throw new RuntimeException('Barrier timed out.');usleep(1000);}
 try{$controller=$app->make(App\Http\Controllers\ShopController::class);$response=$action==='payouts'?$controller->payout($request):$controller->storeOrder($request);echo $response->getStatusCode()."\n";}catch(Illuminate\Validation\ValidationException $e){echo "422\n";}exit;
}
$owner=User::where('role','owner')->firstOrFail();$agent=User::create(['name'=>'AUDIT-CONCURRENCY','phone'=>'099'.random_int(10000000,99999999),'password'=>'TestingPassword!']);$agent->forceFill(['role'=>'agent','status'=>'approved'])->save();$product=DB::table('products')->insertGetId(['name'=>'AUDIT-CONCURRENCY','description'=>'audit','price'=>35000,'commission'=>5000,'stock'=>1,'active'=>true,'image'=>'/products/cream.jpg','created_at'=>now(),'updated_at'=>now()]);
try{
 DB::table('orders')->insert(['agent_id'=>$agent->id,'product_id'=>$product,'customer'=>'fixture','phone'=>'09900000000','address'=>'ရန်ကုန်','quantity'=>2,'unit_price'=>35000,'unit_commission'=>5000,'status'=>'sent','sent_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
 $results=[];
 foreach(['payouts','orders'] as $action){$barrier=sys_get_temp_dir().'/aeae-barrier-'.bin2hex(random_bytes(8));$workers=[];
  for($i=0;$i<2;$i++){$pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'worker',$action,(string)$owner->id,(string)$agent->id,(string)$product,$barrier],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$workers[]=[$process,$pipes];}
  // Both independent connections start their transactions from one barrier.
  $deadline=microtime(true)+10;while(count(glob($barrier.'.*'))<2){if(microtime(true)>$deadline)throw new RuntimeException('Workers did not become ready.');usleep(1000);}file_put_contents($barrier,'go');$statuses=[];foreach($workers as [$process,$pipes]){$text=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0)throw new RuntimeException('Worker failed: '.$error);$statuses[]=(int)trim($text);}unlink($barrier);foreach(glob($barrier.'.*') as $ready)unlink($ready);sort($statuses);if($statuses!==[201,422])throw new RuntimeException('Unexpected parallel results: '.json_encode($statuses));$results[$action]=$statuses;
 }
 $paid=(int)DB::table('payouts')->where('agent_id',$agent->id)->sum('amount');$stock=(int)DB::table('products')->where('id',$product)->value('stock');if($paid!==8000||$stock!==0)throw new RuntimeException('A concurrent money/stock invariant failed.');echo json_encode(['parallel_results'=>$results,'paid'=>$paid,'remaining_stock'=>$stock],JSON_PRETTY_PRINT)."\n";
}finally{DB::table('shop_notifications')->where('recipient_id',$agent->id)->delete();$ids=DB::table('orders')->where('agent_id',$agent->id)->pluck('id');DB::table('shop_notifications')->where('destination','orders')->whereIn('resource_id',$ids)->delete();DB::table('payouts')->where('agent_id',$agent->id)->delete();DB::table('orders')->where('agent_id',$agent->id)->delete();DB::table('products')->where('id',$product)->delete();$agent->delete();}
