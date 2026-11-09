<?php
namespace Database\Seeders;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class DatabaseSeeder extends Seeder {
 public function run():void {
  if(!app()->environment(['local','testing']))throw new \RuntimeException('Sample data is allowed only locally. Use app:create-owner in production.');
  $owner=User::firstOrCreate(['phone'=>'09900000001'],['name'=>'အဲအဲလေး','password'=>'LocalFixture2026!']);$owner->forceFill(['role'=>'owner','status'=>'approved'])->save();
  $names=['မသဇင်','မေသက်ထား','မနှင်းအိ','မစုမြတ်','မခင်ရတနာ','မပန်းအိ','မအိဖြူ'];$agents=[];
  foreach($names as $i=>$name){$u=User::firstOrCreate(['phone'=>'0990000000'.($i+2)],['name'=>$name,'password'=>'LocalFixture2026!']);$u->forceFill(['role'=>'agent','status'=>$i>4?'pending':'approved'])->save();$agents[]=$u;}
  if(DB::table('products')->exists())return;
  $p1=DB::table('products')->insertGetId(['name'=>'နေ့စဉ်အသားအရေထိန်းသိမ်းမှု အစုံ','description'=>'နူးညံ့စိုပြေသော အသားအရေအတွက် သဘာဝပါဝင်ပစ္စည်းများဖြင့် ထုတ်လုပ်ထားသော အလှအပပစ္စည်းအစုံ။','price'=>35000,'commission'=>5000,'stock'=>240,'image'=>'/products/cream.jpg','active'=>true,'created_at'=>now(),'updated_at'=>now()]);
  $p2=DB::table('products')->insertGetId(['name'=>'သဘာဝနှုတ်ခမ်းနီ','description'=>'နေ့စဉ်အလှအတွက် နူးညံ့ပြီး ကြာရှည်ခံသော နှင်းဆီရောင် နှုတ်ခမ်းနီ။','price'=>18000,'commission'=>3000,'stock'=>160,'image'=>'/products/lipstick.jpg','active'=>true,'created_at'=>now(),'updated_at'=>now()]);
  $customers=['မခင်သီတာ','မနန်းအိဖြူ','မယုယုခိုင်','မအိသန္တာ','မဝတ်ရည်','မသင်းသင်း'];
  for($i=47;$i>=0;$i--){$days=$i<7?0:intdiv($i,2);$status=$i<8?'pending':($i===15?'cancelled':'sent');DB::table('orders')->insert(['agent_id'=>$agents[$i%5]->id,'product_id'=>$i%2?$p2:$p1,'customer'=>$customers[$i%6],'phone'=>'0976500'.($i+100),'address'=>'အမှတ် ၂၄၊ ဗဟန်းမြို့နယ်၊ ရန်ကုန်','quantity'=>1+$i%4,'unit_price'=>$i%2?18000:35000,'unit_commission'=>$i%2?3000:5000,'status'=>$status,'created_at'=>now()->subDays($days),'updated_at'=>now(),'sent_at'=>$status==='sent'?now()->subDays(max(0,$days-1)):null]);}
  DB::table('payouts')->insert(['agent_id'=>$agents[0]->id,'amount'=>20000,'reference'=>'ယခင်ငွေရှင်းမှု','created_at'=>now()->subDays(5),'updated_at'=>now()]);
 }
}
