<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(config('database.connections.'.config('database.default').'.database')!=='aeae_testing')throw new RuntimeException('This fixture is restricted to aeae_testing.');
use Illuminate\Support\Facades\{DB,Hash};
require __DIR__.'/browser-fixtures.php';
$hash=Hash::make('LocalFixture2026!');$product=DB::table('products')->first();
for($i=0;$i<35;$i++)DB::table('products')->insert(['name'=>'စမ်းသပ်ပစ္စည်း '.str_pad((string)$i,3,'0',STR_PAD_LEFT),'description'=>'စမ်းသပ်ပစ္စည်း အသေးစိတ်','price'=>35000,'commission'=>5000,'stock'=>500,'active'=>true,'image'=>'/products/cream.jpg','created_at'=>now(),'updated_at'=>now()]);
for($i=0;$i<55;$i++){
 $id=DB::table('users')->insertGetId(['name'=>'စမ်းသပ်ကိုယ်စားလှယ် '.str_pad((string)$i,3,'0',STR_PAD_LEFT),'phone'=>'099555'.str_pad((string)$i,5,'0',STR_PAD_LEFT),'password'=>$hash,'role'=>'agent','status'=>'approved','created_at'=>now(),'updated_at'=>now()]);
 DB::table('orders')->insert(['agent_id'=>$id,'product_id'=>$product->id,'customer'=>'စမ်းသပ်ဝယ်သူ '. $i,'phone'=>'09900000000','address'=>'ရန်ကုန်','quantity'=>10,'unit_price'=>35000,'unit_commission'=>5000,'status'=>'sent','sent_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
 DB::table('payouts')->insert(['agent_id'=>$id,'amount'=>1000,'reference'=>'စမ်းသပ်ငွေရှင်းမှု '.$i,'created_at'=>now(),'updated_at'=>now()]);
}
echo "Isolated audit fixtures prepared.\n";
