<?php
namespace App\Http\Controllers;
use App\Models\{User,WebPushSubscription};
use Illuminate\Http\Request;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\{DB,Hash};
use Illuminate\Support\Str;
use Illuminate\Validation\{Rule,ValidationException};
class AccountController extends Controller {
 private function approved(Request $r):void{abort_unless($r->user()?->status==='approved',403,'ပိုင်ရှင်၏ အတည်ပြုချက်ကို စောင့်ပါ။');}
 private function verifyPassword(string $password,User $user):void{if(!Hash::check($password,$user->password))throw ValidationException::withMessages(['current_password'=>'လက်ရှိစကားဝှက် မမှန်ပါ။']);}
 public function update(Request $r){
  $this->approved($r);$v=$r->validate(['name'=>'required|string|max:100','phone'=>['required','regex:/^09[0-9]{7,11}$/',Rule::unique('users','phone')->ignore($r->user()->id)],'current_password'=>'required|string|max:128','agent_version'=>'required|integer|min:0']);
  try{return DB::transaction(function()use($r,$v){
   $u=User::where('id',$r->user()->id)->lockForUpdate()->firstOrFail();abort_unless($u->status==='approved',403,'ပိုင်ရှင်၏ အတည်ပြုချက်ကို စောင့်ပါ။');$this->verifyPassword($v['current_password'],$u);
   abort_unless((int)$v['agent_version']===(int)$u->agent_version,409,'အကောင့်အချက်အလက် ပြောင်းလဲသွားပါပြီ။ လက်ရှိစာရင်းကို ပြန်ဖွင့်ပြီး ပြင်ဆင်ပါ။');
   $before=['name'=>$u->name,'phone'=>$u->phone];$after=['name'=>$v['name'],'phone'=>$v['phone']];
   if($before!==$after){$u->forceFill([...$after,'agent_version'=>$u->agent_version+1])->save();
    if($u->role==='agent')DB::table('agent_changes')->insert(['agent_id'=>$u->id,'actor_id'=>$u->id,'before'=>json_encode($before,JSON_UNESCAPED_UNICODE),'after'=>json_encode($after,JSON_UNESCAPED_UNICODE),'note'=>'ကိုယ်ပိုင်အကောင့် ပြင်ဆင်ခြင်း','created_at'=>now()]);
   }
   return response()->json($u);
  },5);}catch(UniqueConstraintViolationException){throw ValidationException::withMessages(['phone'=>'ဤ ဖုန်းနံပါတ်ဖြင့် စာရင်းသွင်းပြီး ဖြစ်ပါသည်။']);}
 }
 public function resetAgentPassword(Request $r,int $agent){
  $this->approved($r);abort_unless($r->user()->role==='owner',403,'လုပ်ငန်းပိုင်ရှင်သာ လုပ်ဆောင်နိုင်ပါသည်။');
  $v=$r->validate(['current_password'=>'required|string|max:128','password'=>['required','string','min:10','max:128','confirmed',new \App\Rules\SafePassword],'agent_version'=>'required|integer|min:0']);
  $this->verifyPassword($v['current_password'],$r->user());
  return DB::transaction(function()use($r,$v,$agent){
   $u=User::where('role','agent')->where('id',$agent)->lockForUpdate()->firstOrFail();
   abort_unless((int)$v['agent_version']===(int)$u->agent_version,409,'ကိုယ်စားလှယ်စာရင်း ပြောင်းလဲသွားပါပြီ။ ပြန်ဖွင့်ပြီး ပြင်ဆင်ပါ။');
   $u->forceFill(['password'=>$v['password'],'remember_token'=>Str::random(60),'agent_version'=>$u->agent_version+1])->save();
   WebPushSubscription::where('user_id',$agent)->delete();
   DB::table('agent_changes')->insert(['agent_id'=>$agent,'actor_id'=>$r->user()->id,'before'=>json_encode(['password_reset'=>false]),'after'=>json_encode(['password_reset'=>true]),'note'=>'ပိုင်ရှင်က စကားဝှက် ပြန်သတ်မှတ်ခြင်း','created_at'=>now()]);
   return response()->json(['message'=>'စကားဝှက် ပြန်သတ်မှတ်ပြီးပါပြီ။']);
  },5);
 }
}
