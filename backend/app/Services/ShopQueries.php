<?php
namespace App\Services;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
class ShopQueries {
 public function validate(Request $r):array {
  return $r->validate(['view'=>'sometimes|in:dashboard,orders,products,agents,earnings,leaderboard,settings','status'=>'sometimes|in:all,pending,sent,cancelled','agent_status'=>'sometimes|in:all,approved,pending,rejected','search'=>'nullable|string|max:100','from'=>'nullable|date_format:Y-m-d|after_or_equal:1000-01-01|before_or_equal:9998-12-31','to'=>['nullable','date_format:Y-m-d','after_or_equal:1000-01-01','before_or_equal:9998-12-31',...($r->filled('from')?['after_or_equal:from']:[])],'per_page'=>'sometimes|integer|in:5,10,20,50','orders_page'=>'sometimes|integer|min:1|max:1000000','users_page'=>'sometimes|integer|min:1|max:1000000','products_page'=>'sometimes|integer|min:1|max:1000000','payouts_page'=>'sometimes|integer|min:1|max:1000000','rankings_page'=>'sometimes|integer|min:1|max:1000000','page'=>'sometimes|integer|min:1|max:1000000']);
 }
 public function dates(Builder $q,Request $r,string $column):Builder {
  if($r->filled('from'))$q->where($column,'>=',Carbon::parse($r->query('from'),config('app.timezone'))->startOfDay());
  if($r->filled('to'))$q->where($column,'<',Carbon::parse($r->query('to'),config('app.timezone'))->startOfDay()->addDay());
  return $q;
 }
 public function orders(Request $r,bool $filter=true,bool $withNames=true):Builder {
  $q=DB::table('orders');if($withNames)$q->join('users','users.id','=','orders.agent_id')->join('products','products.id','=','orders.product_id');elseif($filter&&trim($r->query('search',''))!=='')$q->join('users','users.id','=','orders.agent_id');
  if($r->user()->role!=='owner')$q->where('orders.agent_id',$r->user()->id);
  if($filter){$this->dates($q,$r,'orders.created_at');if($r->query('status','all')!=='all')$q->where('orders.status',$r->query('status'));$this->orderSearch($q,trim($r->query('search','')));}
  return $q;
 }
 private function orderSearch(Builder $q,string $search):void {
  if($search==='')return;
  // A literal search: SQL wildcards from input do not expand the result set.
  $like='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';
  $q->where(function($q)use($like,$search){foreach(['orders.customer','orders.phone','users.name'] as $c)$q->orWhereRaw("$c LIKE ? ESCAPE '!'",[$like]);if(ctype_digit($search))$q->orWhere('orders.id',(int)$search);});
 }
 public function productQuery(bool $activeOnly=false):Builder {
  return DB::table('products')->when($activeOnly,fn($q)=>$q->where('active',true))->select(['id','name','description','price','commission','stock','active','lock_version'])->selectRaw("CASE WHEN image IN ('/products/cream.jpg','/products/lipstick.jpg') THEN image ELSE '' END AS image");
 }
 public function productRow($p){$p->active=(bool)$p->active;if($p->image==='')$p->image="/api/products/{$p->id}/image?v={$p->lock_version}";return $p;}
 public function timestamp($row){foreach(['created_at','updated_at','sent_at'] as $key){if(isset($row->$key))$row->$key=Carbon::parse($row->$key,config('app.timezone'))->toIso8601String();}return $row;}
 public function page(Builder $query,Request $r,string $name,?int $size=null):array {
  $size??=(int)$r->query('per_page',10);$p=$query->paginate($size,['*'],$name.'_page',(int)$r->query($name.'_page',1));
  if($p->currentPage()>$p->lastPage())$p=$query->paginate($size,['*'],$name.'_page',$p->lastPage());
  return ['rows'=>$p->items(),'meta'=>['current_page'=>$p->currentPage(),'last_page'=>$p->lastPage(),'per_page'=>$p->perPage(),'total'=>$p->total(),'from'=>$p->firstItem(),'to'=>$p->lastItem()]];
 }
 public function agentBalances(array $ids):array {
  if(!$ids)return [];
  $earned=DB::table('orders')->whereIn('agent_id',$ids)->where('status','sent')->selectRaw('agent_id, SUM(quantity * unit_commission) AS earned, SUM(quantity) AS sold')->groupBy('agent_id')->get()->keyBy('agent_id');
  $opening=DB::table('users')->whereIn('id',$ids)->get(['id','opening_quantity','opening_balance'])->keyBy('id');
  $paid=DB::table('payouts')->whereIn('agent_id',$ids)->selectRaw('agent_id, SUM(amount) AS paid')->groupBy('agent_id')->pluck('paid','agent_id');$result=[];
  foreach($ids as $id){$ob=(int)($opening[$id]->opening_balance??0);$oq=(int)($opening[$id]->opening_quantity??0);$e=(int)($earned[$id]->earned??0)+$ob;$p=(int)($paid[$id]??0);$result[$id]=['earned'=>$e,'paid'=>$p,'balance'=>$e-$p,'sold'=>(int)($earned[$id]->sold??0)+$oq,'opening_quantity'=>$oq,'opening_balance'=>$ob];}return $result;
 }
 public function state(Request $r):array {
  $this->validate($r);$view=$r->query('view','dashboard');$owner=$r->user()->role==='owner';abort_if($view==='agents'&&!$owner,403,'လုပ်ငန်းပိုင်ရှင်သာ လုပ်ဆောင်နိုင်ပါသည်။');
  $out=['users'=>[],'products'=>[],'orders'=>[],'payouts'=>[],'pagination'=>[],'counts'=>['pending_orders'=>0,'pending_agents'=>0,'orders'=>['all'=>0,'pending'=>0,'sent'=>0,'cancelled'=>0],'agents'=>['all'=>0,'approved'=>0,'pending'=>0,'rejected'=>0]],'summary'=>['revenue'=>0,'commission'=>0,'sent_orders'=>0,'earned'=>0,'paid'=>0,'balance'=>0,'opening_balance'=>0]];
  $base=DB::table('orders')->when(!$owner,fn($q)=>$q->where('agent_id',$r->user()->id));
  $out['counts']['pending_orders']=(clone $base)->where('status','pending')->count();
  if($owner){$counts=DB::table('users')->where('role','agent')->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total','status');foreach(['approved','pending','rejected'] as $key)$out['counts']['agents'][$key]=(int)($counts[$key]??0);$out['counts']['agents']['all']=array_sum($out['counts']['agents']);$out['counts']['pending_agents']=$out['counts']['agents']['pending'];}
  if(in_array($view,['dashboard','orders'])){
   $status=$this->dates(clone $base,$r,'created_at')->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total','status');foreach(['pending','sent','cancelled'] as $key)$out['counts']['orders'][$key]=(int)($status[$key]??0);$out['counts']['orders']['all']=array_sum($out['counts']['orders']);
   $ids=$this->orders($r,true,false)->select('orders.id')->orderByDesc('orders.id');$p=$this->page($ids,$r,'orders',$view==='dashboard'?5:null);$rows=$this->orders($r,false)->whereIn('orders.id',array_column($p['rows'],'id'))->select('orders.*','users.name as agent_name','products.name as product_name')->orderByDesc('orders.id')->get()->all();$out['orders']=array_map($this->timestamp(...),$rows);$out['pagination']['orders']=$p['meta'];
  }
  if($view==='dashboard'){
   $sent=$this->dates((clone $base)->where('status','sent'),$r,'sent_at')->selectRaw('COUNT(*) AS sent_orders, COALESCE(SUM(quantity*unit_price),0) AS revenue, COALESCE(SUM(quantity*unit_commission),0) AS commission')->first();foreach(['sent_orders','revenue','commission'] as $key)$out['summary'][$key]=(int)$sent->$key;
  }
  if($view==='products'){$p=$this->page($this->productQuery(!$owner)->orderBy('id'),$r,'products');$out['products']=array_map($this->productRow(...),$p['rows']);$out['pagination']['products']=$p['meta'];}
  if($view==='agents'||($view==='earnings'&&$owner)){
   $q=DB::table('users')->where('role','agent')->select('id','name','phone','role','status','created_at','opening_date','agent_version');if($view==='agents'&&$r->query('agent_status','approved')!=='all')$q->where('status',$r->query('agent_status','approved'));
   if($search=trim($r->query('search',''))){$like='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';$q->where(fn($q)=>$q->whereRaw("name LIKE ? ESCAPE '!'",[$like])->orWhereRaw("phone LIKE ? ESCAPE '!'",[$like]));}
   $p=$this->page($view==='agents'&&$r->query('agent_status','approved')==='pending'?$q->orderByDesc('id'):$q->orderBy('id'),$r,'users');$balances=$this->agentBalances(array_column($p['rows'],'id'));$out['users']=array_map(fn($u)=>(object)[...(array)$this->timestamp($u),...$balances[$u->id]],$p['rows']);$out['pagination']['users']=$p['meta'];

  }
  if($view==='earnings'){
   $pay=DB::table('payouts')->join('users','users.id','=','payouts.agent_id')->when(!$owner,fn($q)=>$q->where('agent_id',$r->user()->id));
   $out['summary']['opening_balance']=(int)DB::table('users')->where('role','agent')->when(!$owner,fn($q)=>$q->where('id',$r->user()->id))->sum('opening_balance');
   $out['summary']['earned']=$out['summary']['opening_balance']+(int)(clone $base)->where('status','sent')->selectRaw('COALESCE(SUM(quantity*unit_commission),0) AS total')->value('total');$out['summary']['paid']=(int)(clone $pay)->sum('amount');$out['summary']['balance']=$out['summary']['earned']-$out['summary']['paid'];
   $this->dates($pay,$r,'payouts.created_at');$p=$this->page($pay->select('payouts.*','users.name AS agent_name')->orderByDesc('payouts.id'),$r,'payouts');$out['payouts']=array_map($this->timestamp(...),$p['rows']);$out['pagination']['payouts']=$p['meta'];
  }
  return $out;
 }
 public function rankings(Request $r):array {
  $this->validate($r);$orders=$this->dates(DB::table('orders')->where('status','sent'),$r,'sent_at')->selectRaw('agent_id, SUM(quantity) AS total')->groupBy('agent_id');
  $q=DB::table('users')->leftJoinSub($orders,'totals','users.id','=','totals.agent_id')->where('users.role','agent')->where('users.status','approved')->select('users.id','users.name')->selectRaw($r->filled('from')||$r->filled('to')?'COALESCE(totals.total,0) AS total':'COALESCE(totals.total,0) + users.opening_quantity AS total')->orderByDesc('total')->orderBy('users.id');$p=$this->page($q,$r,'rankings');foreach($p['rows'] as $row)$row->total=(int)$row->total;return ['data'=>$p['rows'],'meta'=>$p['meta']];
 }
}
