import {useEffect,useRef,useState,type FormEvent} from 'react';
import {Loader2} from 'lucide-react';
import {money,mm,day,type User,type PageMeta} from './data';
import Pagination from './Pagination';
import type {ApiRequest} from './pwa';
type Snapshot=Pick<User,'name'|'phone'|'status'|'opening_quantity'|'opening_balance'|'opening_date'>&{password_reset?:boolean};
type Change={id:number;before:Snapshot;after:Snapshot;actor_name:string;note:string|null;created_at:string};
const fields:Record<string,string>={password_reset:'စကားဝှက်',name:'အမည်',phone:'ဖုန်းနံပါတ်',status:'အခြေအနေ',opening_quantity:'အရင်ရောင်းပြီး အရေအတွက်',opening_balance:'ပေးရန်အစလက်ကျန်',opening_date:'အစလက်ကျန်သတ်မှတ်ရက်'};
const statuses={approved:'အတည်ပြုပြီး',pending:'အတည်ပြုရန်',rejected:'ရပ်ဆိုင်းထား'};
const dateLabel=(input:string)=>{const value=input.length===10?input+'T00:00:00+06:30':input;const date=new Date(value);return Number.isFinite(date.getTime())?`${day(value)}၊ ${mm(new Date(date.getTime()+390*60000).getUTCFullYear())}`:'—'};
const today=()=>new Date(Date.now()+390*60000).toISOString().slice(0,10);
function value(field:string,value:unknown){if(field==='password_reset')return value?'ပြန်သတ်မှတ်ပြီး':'—';if(value===null||value===undefined||value==='')return '—';if(field==='opening_balance')return money(Number(value));if(field==='opening_quantity')return `${mm(Number(value))} ခု`;if(field==='status')return statuses[value as keyof typeof statuses];if(field==='opening_date')return dateLabel(String(value));return String(value);}
export default function AgentEditor({agentId,request,busy,onBusy,onSaved}:{agentId:number;request:ApiRequest;busy:boolean;onBusy:(v:boolean)=>void;onSaved:()=>void}){
 const latest=useRef(request);latest.current=request;
 const [agent,setAgent]=useState<User|null>(null),[error,setError]=useState(''),[loading,setLoading]=useState(true),[conflict,setConflict]=useState(false),[reload,setReload]=useState(0);
 const [quantity,setQuantity]=useState('0'),[balance,setBalance]=useState('0'),[date,setDate]=useState('');
 const [resetOpen,setResetOpen]=useState(false),[resetError,setResetError]=useState(''),[resetSuccess,setResetSuccess]=useState('');
 const [historyOpen,setHistoryOpen]=useState(false),[page,setPage]=useState(1),[history,setHistory]=useState<Change[]>([]),[meta,setMeta]=useState<PageMeta>(),[historyLoading,setHistoryLoading]=useState(false),[historyError,setHistoryError]=useState(''),[historyRetry,setHistoryRetry]=useState(0);
 useEffect(()=>{const controller=new AbortController();setLoading(true);setError('');setConflict(false);setAgent(null);latest.current('/agents/'+agentId,'GET',undefined,controller.signal).then(u=>{setAgent(u);setQuantity(String(u.opening_quantity));setBalance(String(u.opening_balance));setDate(u.opening_date||'')}).catch(e=>{if(!controller.signal.aborted)setError(e.message)}).finally(()=>{if(!controller.signal.aborted)setLoading(false)});return()=>controller.abort()},[agentId,reload]);
 useEffect(()=>{if(!historyOpen)return;const controller=new AbortController();setHistoryLoading(true);setHistoryError('');latest.current(`/agents/${agentId}/history?page=${page}`,'GET',undefined,controller.signal).then(body=>{setHistory(body.data);setMeta(body.meta)}).catch(e=>{if(!controller.signal.aborted)setHistoryError(e.message)}).finally(()=>{if(!controller.signal.aborted)setHistoryLoading(false)});return()=>controller.abort()},[agentId,historyOpen,page,historyRetry]);
 const openingChanged=!!agent&&(Number(quantity)!==(agent.opening_quantity||0)||Number(balance)!==(agent.opening_balance||0)||date!==(agent.opening_date||''));
 async function submit(e:FormEvent<HTMLFormElement>){e.preventDefault();if(!agent||busy)return;const form=new FormData(e.currentTarget);onBusy(true);setError('');try{await latest.current('/agents/'+agent.id,'PUT',{name:String(form.get('name')).trim(),phone:String(form.get('phone')).trim(),status:form.get('status'),opening_quantity:Number(quantity),opening_balance:Number(balance),opening_date:date||null,agent_version:agent.agent_version,note:String(form.get('note')).trim()});onSaved()}catch(e){setError((e as Error).message);if((e as Error&{status?:number}).status===409)setConflict(true)}finally{onBusy(false)}}
 async function resetPassword(e:FormEvent<HTMLFormElement>){e.preventDefault();if(busy||!agent)return;const form=e.currentTarget;const body=Object.fromEntries(new FormData(form));onBusy(true);setResetError('');setResetSuccess('');try{await latest.current('/agents/'+agent.id+'/password','PUT',{...body,agent_version:agent.agent_version});form.reset();setResetOpen(false);setResetSuccess('စကားဝှက် ပြန်သတ်မှတ်ပြီးပါပြီ။');setHistoryRetry(v=>v+1);setReload(v=>v+1)}catch(e){setResetError((e as Error).message);if((e as Error&{status?:number}).status===409)setConflict(true)}finally{onBusy(false)}}
 if(loading)return <div className="empty" role="status"><Loader2 size={22} className="spin"/>စာရင်းရယူနေသည်…</div>;
 if(!agent)return <div className="agent-editor-error"><p className="form-error" role="alert">{error}</p><button className="button secondary" onClick={()=>setReload(v=>v+1)}>ပြန်ကြိုးစားမည်</button></div>;
 return <div className="agent-editor"><form key={`${agent.id}-${agent.agent_version}`} onSubmit={submit}>
  <fieldset disabled={busy}>
   <label>အမည်<input name="name" required maxLength={100} defaultValue={agent.name} autoComplete="name"/></label>
   <label>ဖုန်းနံပါတ်<input name="phone" type="tel" inputMode="tel" required pattern="09[0-9]{7,11}" maxLength={13} defaultValue={agent.phone} autoComplete="tel"/></label>
   <label>အခြေအနေ<select name="status" defaultValue={agent.status}>{Object.entries(statuses).map(([id,label])=><option value={id} key={id}>{label}</option>)}</select></label>
   <section className="opening-fields" aria-label="အစလက်ကျန်"><h3>အက်ပ်မသုံးမီ စာရင်း</h3>
    <label>အရင်ရောင်းပြီး အရေအတွက်<input name="opening_quantity" type="number" inputMode="numeric" min="0" max="1000000000" step="1" required value={quantity} onChange={e=>setQuantity(e.target.value)}/></label>
    <label>ပေးရန်အစလက်ကျန် (ကျပ်)<input name="opening_balance" type="number" inputMode="numeric" min="0" max="1000000000000" step="1" required value={balance} onChange={e=>setBalance(e.target.value)}/><small>အရင်က ပေးပြီးငွေကို နုတ်ထားသော ကျန်ငွေကိုသာ ဖြည့်ပါ။</small></label>
    <label>အစလက်ကျန်သတ်မှတ်ရက်<input name="opening_date" type="date" min="1000-01-01" max={today()} required={Number(quantity)>0||Number(balance)>0} value={date} onChange={e=>setDate(e.target.value)}/></label>
   </section>
   <label>ပြင်ဆင်ရသည့်အကြောင်းရင်း<input name="note" maxLength={250} required={openingChanged} placeholder={openingChanged?'အစလက်ကျန် ထည့်သွင်းခြင်း / ပြင်ဆင်ခြင်း':'မှတ်ချက် (ရှိလျှင်)'}/></label>
  </fieldset>
  {error&&<p className="form-error" role="alert">{error}</p>}
  {conflict&&<button type="button" className="button secondary full" disabled={busy} onClick={()=>setReload(v=>v+1)}>လက်ရှိစာရင်း ပြန်ဖွင့်မည်</button>}
  <button className="button primary full" disabled={busy||conflict}>{busy&&<Loader2 size={17} className="spin"/>}သိမ်းဆည်းမည်</button>
 </form>
 <section className="agent-password-reset"><button type="button" className="button secondary full" disabled={busy} onClick={()=>{setResetOpen(!resetOpen);setResetError('')}}>စကားဝှက် ပြန်သတ်မှတ်မည်</button>{resetOpen&&<form onSubmit={resetPassword} aria-label="ကိုယ်စားလှယ် စကားဝှက် ပြန်သတ်မှတ်ရန်"><p className="form-note">ကိုယ်စားလှယ်၏ အခြားစက်များတွင် အကောင့်ဝင်ထားမှု ပယ်ဖျက်မည်။</p><fieldset disabled={busy}><label>ပိုင်ရှင်၏ လက်ရှိစကားဝှက်<input name="current_password" type="password" autoComplete="current-password" required maxLength={128}/></label><label>စကားဝှက်အသစ်<input name="password" type="password" autoComplete="new-password" minLength={10} maxLength={128} required/></label><label>စကားဝှက်အသစ် ထပ်ဖြည့်ပါ<input name="password_confirmation" type="password" autoComplete="new-password" minLength={10} maxLength={128} required/></label></fieldset>{resetError&&<p className="form-error" role="alert">{resetError}</p>}<button className="button primary full" disabled={busy||conflict}>စကားဝှက်အသစ် သတ်မှတ်မည်</button></form>}{resetSuccess&&<p className="form-note" role="status">{resetSuccess}</p>}</section>
 <details className="agent-history" open={historyOpen} onToggle={e=>setHistoryOpen(e.currentTarget.open)}><summary>ပြင်ဆင်မှုမှတ်တမ်း</summary>
  {historyLoading?<p role="status">စာရင်းရယူနေသည်…</p>:historyError?<div><p className="form-error" role="alert">{historyError}</p><button className="text-button" onClick={()=>setHistoryRetry(v=>v+1)}>ပြန်ကြိုးစားမည်</button></div>:<><div className="agent-change-list">{history.map(change=><article className="agent-change" key={change.id}><header><strong>{change.actor_name}</strong><time>{dateLabel(change.created_at)}</time></header><dl>{Object.keys(fields).filter(field=>change.before[field as keyof Snapshot]!==change.after[field as keyof Snapshot]).map(field=><div key={field}><dt>{fields[field]}</dt><dd><span>{value(field,change.before[field as keyof Snapshot])}</span><b aria-label="မှ">→</b><strong>{value(field,change.after[field as keyof Snapshot])}</strong></dd></div>)}</dl>{change.note&&<p>{change.note}</p>}</article>)}</div>{!history.length&&<p>ပြင်ဆင်မှု မရှိသေးပါ။</p>}</>}
  <Pagination meta={meta} disabled={historyLoading} compact onPage={setPage}/>
 </details></div>;
}
