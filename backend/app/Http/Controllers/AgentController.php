<?php
namespace App\Http\Controllers;
use App\Models\{User,WebPushSubscription};
use App\Services\{ShopQueries,ShopNotifications};
use Illuminate\Http\Request;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\{Rule,ValidationException};
class AgentController extends Controller {
 private const FIELDS=['name','phone','status','opening_quantity','opening_balance','opening_date'];
 public function __construct(private ShopQueries $queries,private ShopNotifications $notifications){}
 private function owner(Request $r):void{abort_unless($r->user()?->role==='owner'&&$r->user()?->status==='approved',403,'လုပ်ငန်းပိုင်ရှင်သာ လုပ်ဆောင်နိုင်ပါသည်။');}
 private function snapshot(User $u):array{return ['name'=>$u->name,'phone'=>$u->phone,'status'=>$u->status,'opening_quantity'=>(int)$u->opening_quantity,'opening_balance'=>(int)$u->opening_balance,'opening_date'=>$u->opening_date];}
 public function show(Request $r,int $agent){
  $this->owner($r);$u=User::where('role','agent')->findOrFail($agent);
  return response()->json(['id'=>$u->id,'role'=>'agent','agent_version'=>$u->agent_version,...$this->snapshot($u),...$this->queries->agentBalances([$u->id])[$u->id]]);
 }
 public function history(Request $r,int $agent){
  $this->owner($r);abort_unless(User::where('role','agent')->where('id',$agent)->exists(),404);
  $r->validate(['page'=>'sometimes|integer|min:1|max:1000000']);
  $q=DB::table('agent_changes')->join('users','users.id','=','agent_changes.actor_id')->where('agent_id',$agent)->select('agent_changes.*','users.name AS actor_name')->orderByDesc('agent_changes.id');
  $p=$q->paginate(10);if($p->currentPage()>$p->lastPage())$p=$q->paginate(10,['*'],'page',$p->lastPage());
  $rows=$p->getCollection()->map(function($row){$row->before=json_decode($row->before,true);$row->after=json_decode($row->after,true);return $this->queries->timestamp($row);});
  return response()->json(['data'=>$rows,'meta'=>['current_page'=>$p->currentPage(),'last_page'=>$p->lastPage(),'per_page'=>10,'total'=>$p->total(),'from'=>$p->firstItem(),'to'=>$p->lastItem()]]);
 }
 public function update(Request $r,int $agent){return $this->save($r,$agent,true);}
 public function status(Request $r,int $agent){return $this->save($r,$agent,false);}
 private function save(Request $r,int $agent,bool $full){
  $this->owner($r);
  $rules=['status'=>'required|in:'.($full?'approved,pending,rejected':'approved,rejected'),'agent_version'=>($full?'required':'sometimes').'|integer|min:0','note'=>'nullable|string|max:250'];
  if($full)$rules+=[
   'name'=>'required|string|max:100','phone'=>['required','regex:/^09[0-9]{7,11}$/',Rule::unique('users','phone')->ignore($agent)],
   'opening_quantity'=>'required|integer|min:0|max:1000000000','opening_balance'=>'required|integer|min:0|max:1000000000000',
   'opening_date'=>'nullable|date_format:Y-m-d|after_or_equal:1000-01-01|before_or_equal:today',
  ];
  $v=$r->validate($rules);
  try{return DB::transaction(function()use($r,$agent,$v,$full){
   // The same user lock is used by payouts and order transitions.
   $u=User::where('role','agent')->where('id',$agent)->lockForUpdate()->firstOrFail();
   if(isset($v['agent_version']))abort_unless((int)$v['agent_version']===(int)$u->agent_version,409,'ကိုယ်စားလှယ်စာရင်း ပြောင်းလဲသွားပါပြီ။ ပြန်ဖွင့်ပြီး ပြင်ဆင်ပါ။');
   $before=$this->snapshot($u);$after=$before;
   foreach(self::FIELDS as $field)if(array_key_exists($field,$v))$after[$field]=in_array($field,['opening_quantity','opening_balance'])?(int)$v[$field]:$v[$field];
   if($full){
    if(($after['opening_quantity']>0||$after['opening_balance']>0)&&!$after['opening_date'])throw ValidationException::withMessages(['opening_date'=>'အစလက်ကျန်သတ်မှတ်ရက်ကို ဖြည့်ပါ။']);
    $openingChanged=false;foreach(['opening_quantity','opening_balance','opening_date'] as $field)if($after[$field]!==$before[$field])$openingChanged=true;
    if($openingChanged&&trim($v['note']??'')==='')throw ValidationException::withMessages(['note'=>'အစလက်ကျန် ပြင်ဆင်ရသည့်အကြောင်းရင်းကို ဖြည့်ပါ။']);
    if($after['opening_balance']!==$before['opening_balance']){
     $balance=$this->queries->agentBalances([$agent])[$agent]['balance']+$after['opening_balance']-$before['opening_balance'];
     if($balance<0)throw ValidationException::withMessages(['opening_balance'=>'ငွေရှင်းပြီးပမာဏထက် ကော်မရှင်စုစုပေါင်း နည်းသွားမည်ဖြစ်၍ အစလက်ကျန်ကို ထိုပမာဏအထိ လျှော့၍ မရပါ။']);
    }
   }
   if($after===$before)return response()->json(['message'=>'ပြောင်းလဲမှု မရှိပါ။','agent_version'=>$u->agent_version]);
   $u->forceFill([...$after,'agent_version'=>$u->agent_version+1])->save();
   DB::table('agent_changes')->insert(['agent_id'=>$agent,'actor_id'=>$r->user()->id,'before'=>json_encode($before,JSON_UNESCAPED_UNICODE),'after'=>json_encode($after,JSON_UNESCAPED_UNICODE),'note'=>trim($v['note']??'')?:null,'created_at'=>now()]);
   if($before['status']!==$after['status']&&$after['status']==='approved')$this->notifications->record($agent,'agent_approved','အကောင့် အတည်ပြုပြီးပါပြီ','အကောင့်ဝင်ပြီး အော်ဒါတင်နိုင်ပါပြီ။','dashboard');
   if($after['status']!=='approved')WebPushSubscription::where('user_id',$agent)->delete();
   return response()->json(['message'=>'ကိုယ်စားလှယ်စာရင်း သိမ်းဆည်းပြီးပါပြီ။','agent_version'=>$u->agent_version]);
  },5);}catch(UniqueConstraintViolationException){throw ValidationException::withMessages(['phone'=>'ဤ ဖုန်းနံပါတ်ဖြင့် စာရင်းသွင်းပြီး ဖြစ်ပါသည်။']);}
 }
}
