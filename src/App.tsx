import { useState, useEffect, useRef, type FormEvent, type ReactNode } from 'react';
import { LayoutDashboard, ShoppingBag, Package, Users, Wallet, Trophy, Settings, LogOut, ChevronDown, ChevronRight, ArrowRight, Plus, Search, Bell, Download, Check, X, Truck, Clock, Menu, Flower2, HelpCircle, Copy, Eye, EyeOff, CheckCircle2, Loader2, Pencil, RefreshCw } from 'lucide-react';
import { mm, money, day, balances, statusText, API, type Data, type User, type Product, type Order, emptyData, type PageMeta, type ListName } from './data';
import DateFilter from './DateFilter';
import { presetRange, rangeQuery, type DateRange } from './dateRange';
import Pagination from './Pagination';
import OptionSelect from './OptionSelect';
import {timedSignal,requestId} from './requestSupport';
import Notifications from './Notifications';
import DeviceFeatures,{useDeviceFeatures} from './DeviceFeatures';
import {currentPushEndpoint,clearDeviceAfterLogout} from './pwa';
const firstPages=()=>({orders:1,users:1,products:1,payouts:1,rankings:1});
type Rank = {
    id: number;
    name: string;
    total: number;
};
type Page = 'dashboard' | 'orders' | 'products' | 'agents' | 'earnings' | 'leaderboard' | 'settings';
const nav = [{ id: 'dashboard', name: 'အနှစ်ချုပ်', icon: LayoutDashboard }, { id: 'orders', name: 'အော်ဒါများ', icon: ShoppingBag }, { id: 'products', name: 'ကုန်ပစ္စည်းများ', icon: Package }, { id: 'agents', name: 'ကိုယ်စားလှယ်များ', icon: Users }, { id: 'earnings', name: 'ကော်မရှင်စာရင်း', icon: Wallet }, { id: 'leaderboard', name: 'အရောင်းအဆင့်များ', icon: Trophy }] as const;
function Logo({ small = false }: {
    small?: boolean;
}) { return <div className={`brand ${small ? 'small' : ''}`}><span className="brand-flower"><Flower2 size={small ? 25 : 34} strokeWidth={1.25}/></span><div><strong>အဲအဲလေး</strong></div></div>; }
function Avatar({ name, index = 0 }: {
    name: string;
    index?: number;
}) { return <span className={`avatar tone-${index % 5}`}>{name.slice(0, 2)}</span>; }
function Modal({title,children,onClose,busy=false}:{title:string;children:ReactNode;onClose:()=>void;busy?:boolean}){
 const latest=useRef({onClose,busy});latest.current={onClose,busy};
 useEffect(()=>{const previous=document.activeElement as HTMLElement|null;const key=(e:KeyboardEvent)=>{if(e.key==='Escape'&&!latest.current.busy)latest.current.onClose();if(e.key==='Tab'){const els=Array.from(document.querySelectorAll<HTMLElement>('.dialog button,.dialog input:not([type=hidden]),.dialog select,.dialog textarea,.dialog a[href]')).filter(el=>!el.hasAttribute('disabled')&&el.offsetParent!==null);if(!els.length)return;if(e.shiftKey&&document.activeElement===els[0]){e.preventDefault();els[els.length-1]?.focus()}else if(!e.shiftKey&&document.activeElement===els[els.length-1]){e.preventDefault();els[0].focus()}}};document.addEventListener('keydown',key);document.body.style.overflow='hidden';document.querySelector<HTMLElement>('.dialog button')?.focus();return()=>{document.removeEventListener('keydown',key);document.body.style.overflow='';previous?.focus()}},[]);
 return <div className="modal-overlay" onMouseDown={e=>{if(e.target===e.currentTarget&&!busy)onClose()}}><section className="dialog" role="dialog" aria-modal="true" aria-label={title}><header><h2>{title}</h2><button className="icon-button" disabled={busy} onClick={onClose} aria-label="ပိတ်မည်"><X size={20}/></button></header>{children}</section></div>;
}
export default function App() {
    const [data, setData] = useState<Data>(emptyData);
    const [user, setUser] = useState<User | null>(null), [authLoading, setAuthLoading] = useState(true), [authMessage, setAuthMessage] = useState(''), [rankings, setRankings] = useState<Rank[]>([]), [rankLoading, setRankLoading] = useState(false), [rankError, setRankError] = useState('');
    const [page, _setPage] = useState<Page>('dashboard'), [sidebar, setSidebar] = useState(false), [toast, setToast] = useState(''), [busy, setBusy] = useState(false), [error, setError] = useState('');
    const [modal, setModal] = useState<'order' | 'product' | 'payout' | 'detail' | 'help' | null>(null), [editProduct, setEditProduct] = useState<Product | null>(null), [detail, setDetail] = useState<Order | null>(null), [payoutAgent, setPayoutAgent] = useState<User | null>(null), [authSignup, setAuthSignup] = useState(() => new URLSearchParams(location.search).get('register') === '1'), [showPassword, setShowPassword] = useState(false), [profile, setProfile] = useState(false);
    const [search, setSearch] = useState(''), [filter, _setFilter] = useState('pending'), [range, _setRange] = useState<DateRange>(() => presetRange('month')), [agentTab, _setAgentTab] = useState('approved'), [quantity, setQuantity] = useState(1);
    const [pages,setPages]=useState(firstPages),[perPage,setPerPage]=useState(10),[debouncedSearch,setDebouncedSearch]=useState(''),[loading,setLoading]=useState(false),[loadError,setLoadError]=useState(''),[revision,setRevision]=useState(0),[rankMeta,setRankMeta]=useState<PageMeta>();
    const [chosenProduct,setChosenProduct]=useState<Product|null>(null),[chosenAgent,setChosenAgent]=useState<User|null>(null),[requestKey,setRequestKey]=useState(''),[passwordForm,setPasswordForm]=useState(false),[optionRevision,setOptionRevision]=useState(0),[fileError,setFileError]=useState(''),[imageReading,setImageReading]=useState(false);
    const readSequence=useRef(0),toastTimer=useRef<ReturnType<typeof setTimeout>|undefined>(undefined);
    const isOwner=user?.role==='owner';
    const resetPages=()=>setPages(firstPages());
    const setFilter=(v:string)=>{_setFilter(v);resetPages()};
    const setAgentTab=(v:string)=>{_setAgentTab(v);resetPages()};
    const setRange=(v:DateRange)=>{_setRange(v);resetPages()};
    const setPage=(p:Page)=>{_setPage(p);resetPages()};
    const notify=(s:string)=>{clearTimeout(toastTimer.current);setToast(s);toastTimer.current=setTimeout(()=>setToast(''),4000)};
    const changePage=(p:Page)=>{setPage(p);setSidebar(false);setSearch('');setDebouncedSearch('');setFilter(p==='dashboard'?'pending':'all');setData(emptyData());setLoadError('')};
    const query=new URLSearchParams({view:page,status:filter,agent_status:agentTab,search:debouncedSearch,per_page:String(perPage)});for(const [name,value] of Object.entries(pages))query.set(name+'_page',String(value));
    const stateQuery=query.toString()+'&'+rangeQuery(range);
    const dataPending=loading||search.trim()!==debouncedSearch;
    const pageSize=(size:number)=>{setPerPage(size);resetPages()};
    const listPage=(name:ListName,value:number)=>setPages(p=>({...p,[name]:value}));
    const pager=(name:ListName,meta=data.pagination[name])=><Pagination meta={meta} disabled={dataPending||busy} perPage={perPage} onSize={pageSize} onPage={value=>listPage(name,value)}/>;
    async function request(path:string,method='GET',body?:unknown,signal?:AbortSignal){
      let csrf='';const timed=timedSignal(signal);const combined=timed.signal;try{
      if(method!=='GET'){const c=await fetch(`${API}/csrf`,{credentials:'include',headers:{Accept:'application/json'},signal:combined});if(!c.ok)throw new Error('ဆာဗာနှင့် ချိတ်ဆက်၍ မရပါ။');csrf=(await c.json()).token;}
      const res=await fetch(`${API}${path}`,{method,credentials:'include',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':csrf},body:body===undefined?undefined:JSON.stringify(body),signal:combined});
      const json=await res.json().catch(()=>({message:'ဆာဗာနှင့် ချိတ်ဆက်၍ မရပါ။'}));
      if(res.status===401||(res.status===403&&(path.startsWith('/state')||path==='/me'||path.startsWith('/rankings')||path.startsWith('/notifications')||path.startsWith('/push')))){setUser(null);setData(emptyData());setModal(null)}
      if(!res.ok)throw Object.assign(new Error(json.message||'တစ်ခုခု မှားယွင်းနေပါသည်။'),{status:res.status});return json;}catch(e){if(e instanceof TypeError)throw new Error('ဆာဗာနှင့် ချိတ်ဆက်၍ မရပါ။');throw e;}finally{timed.dispose()}
    }
    const features=useDeviceFeatures(user?.id||null,request);
    const activeUserId=useRef(user?.id);activeUserId.current=user?.id;
    const pendingNavigation=useRef<{destination:string;resource_id:number|null}|null>((()=>{const q=new URLSearchParams(location.search);const destination=q.get('page');return destination?{destination,resource_id:Number(q.get('order'))||null}:null})());
    const navigation=useRef<(page:string,id:number|null)=>void>(()=>{});
    navigation.current=(destination,id)=>{const allowed=['dashboard','orders','agents','earnings'];if(!allowed.includes(destination))return;if(!user){pendingNavigation.current={destination,resource_id:id};return;}if(destination==='agents'&&!isOwner)destination='dashboard';setRange(presetRange('all'));changePage(destination as Page);if(destination==='agents')setAgentTab('pending');if(destination==='orders'&&id){const account=user.id;request('/orders/'+id).then(order=>{if(activeUserId.current===account){setError('');setDetail(order);setModal('detail')}}).catch(e=>notify(e.message));}};
    useEffect(()=>{if(!user||!pendingNavigation.current)return;const pending=pendingNavigation.current;pendingNavigation.current=null;navigation.current(pending.destination,pending.resource_id)},[user]);
    useEffect(()=>{const clicked=(event:MessageEvent)=>{if(event.data?.type==='NAVIGATE_NOTIFICATION')navigation.current(event.data.destination,event.data.resource_id)};navigator.serviceWorker?.addEventListener('message',clicked);return()=>navigator.serviceWorker?.removeEventListener('message',clicked)},[]);
    async function refresh(){setRevision(v=>v+1)}
    useEffect(()=>{const timer=setTimeout(()=>{setDebouncedSearch(search.trim());resetPages()},300);return()=>clearTimeout(timer)},[search]);
    useEffect(()=>()=>{clearTimeout(toastTimer.current)},[]);
    useEffect(()=>{if(!user)return;const controller=new AbortController();const seq=++readSequence.current;setLoading(true);setLoadError('');request('/state?'+stateQuery,'GET',undefined,controller.signal).then(d=>{if(seq===readSequence.current)setData(d)}).catch(e=>{if(e.name!=='AbortError'&&seq===readSequence.current){setData(emptyData());setLoadError(e.name==='TimeoutError'?'ချိတ်ဆက်မှုကြာနေပါသည်။ ပြန်ကြိုးစားပါ။':e.message)}}).finally(()=>{if(!controller.signal.aborted&&seq===readSequence.current)setLoading(false)});return()=>controller.abort()},[user,stateQuery,revision]);
    useEffect(()=>{if(!user)return;const check=()=>{if(document.visibilityState==='visible')request('/me').then(()=>setRevision(v=>v+1)).catch(()=>{})};window.addEventListener('focus',check);window.addEventListener('pageshow',check);return()=>{window.removeEventListener('focus',check);window.removeEventListener('pageshow',check)}},[user]);
    useEffect(()=>{let active=true;const controller=new AbortController();localStorage.removeItem('aeae-demo');request('/me','GET',undefined,controller.signal).then(account=>{if(active)setUser(account)}).catch(e=>{if(active&&e.name!=='AbortError'&&e.status!==401&&e.status!==403)setError(e.name==='TimeoutError'?'ချိတ်ဆက်မှုကြာနေပါသည်။ ပြန်ကြိုးစားပါ။':e.message)}).finally(()=>{if(active)setAuthLoading(false)});return()=>{active=false;controller.abort()}},[]);
    useEffect(()=>{if(!user||page!=='leaderboard')return;const controller=new AbortController();setRankLoading(true);setRankError('');const q=rangeQuery(range)+'&rankings_page='+pages.rankings+'&per_page='+perPage;request('/rankings?'+q,'GET',undefined,controller.signal).then(body=>{setRankings(body.data);setRankMeta(body.meta)}).catch(e=>{if(e.name!=='AbortError'){setRankings([]);setRankError(e.message)}}).finally(()=>{if(!controller.signal.aborted)setRankLoading(false)});return()=>controller.abort()},[user,page,range,pages.rankings,perPage,revision]);
    async function logout() { setBusy(true); try {
        await request('/logout','POST',{push_endpoint:await currentPushEndpoint()});
        void clearDeviceAfterLogout();
        setUser(null);
        setData(emptyData());
        setRankings([]);
        setModal(null);
        setProfile(false);
        setSidebar(false);
        setAuthSignup(false);
        setAuthMessage('');
        setError('');
        setPage('dashboard');
    }
    catch (e) {
        notify((e as Error).message);
    }
    finally {
        setBusy(false);
    } }
    async function mutate(path: string, method: string, body: unknown, message: string) {if(busy)return false; setBusy(true); setError(''); try {
        await request(path, method, body);
        await refresh();
        notify(message);
        return true;
    }
    catch (e) {
        setError((e as Error).message);
        if((e as Error&{status?:number}).status===409){setRevision(v=>v+1);if(modal==='order'){setChosenProduct(null);setOptionRevision(v=>v+1)}}
        notify((e as Error).name==='TimeoutError'?'ချိတ်ဆက်မှုကြာနေပါသည်။ ပြန်ကြိုးစားပါ။':(e as Error).message);
        return false;
    }
    finally {
        setBusy(false);
    } }
    const allOrders=data.orders,filteredOrders=data.orders,ranks=rankings;
    const pendingAgents=data.counts.pending_agents,pendingOrders=data.counts.pending_orders;
    const revenue=data.summary.revenue,commission=data.summary.commission;
    const openOrder=(product:Product|null=null)=>{setError('');setQuantity(1);setChosenProduct(product);setChosenAgent(null);setRequestKey(requestId());setModal('order')};
    const pSelected=chosenProduct;
    const openProduct = (p: Product | null = null) => { setError(''); setEditProduct(p);setFileError(''); setModal('product'); };
    const updateStatus = async (o: Order, status: Order['status']) => { if(await mutate(`/orders/${o.id}/status`, 'PATCH', { status }, status === 'sent' ? 'အော်ဒါကို ပို့ပြီးအဖြစ် မှတ်သားပြီးပါပြီ။' : 'အော်ဒါကို ပယ်ဖျက်ပြီးပါပြီ။'))setModal(null); };
    async function submitOrder(e: FormEvent<HTMLFormElement>) { e.preventDefault(); if (!user)
        return; const f = new FormData(e.currentTarget), p = chosenProduct!; const q = Number(f.get('quantity')); if (!p || q < 1 || q > p.stock) {
        setError('လက်ကျန်အရေအတွက်ကို ပြန်စစ်ပေးပါ။');
        return;
    } const fields = { request_key:requestKey,expected_price:p.price,expected_commission:p.commission,agent_id: isOwner ? chosenAgent?.id : user.id, product_id: p.id, customer: String(f.get('customer')).trim(), phone: String(f.get('phone')).trim(), address: String(f.get('address')).trim(), quantity: q }; if (await mutate('/orders', 'POST', fields, 'အော်ဒါအသစ်ကို သိမ်းဆည်းပြီးပါပြီ။'))
        setModal(null); }
    async function submitProduct(e: FormEvent<HTMLFormElement>) { e.preventDefault();if(imageReading)return;if(fileError){setError(fileError);return;} const f = new FormData(e.currentTarget); const p = { name: String(f.get('name')).trim(), description: String(f.get('description')), price: Number(f.get('price')), commission: Number(f.get('commission')), stock: Number(f.get('stock')), image: String(f.get('image')), active:editProduct?.active??true, ...(editProduct?{lock_version:editProduct.lock_version}:{}) }; if (p.commission > p.price) {
        setError('ကော်မရှင်သည် ရောင်းဈေးထက် မများရပါ။');
        return;
    } if (await mutate(editProduct ? `/products/${editProduct.id}` : '/products', editProduct ? 'PUT' : 'POST', p, 'ကုန်ပစ္စည်းကို သိမ်းဆည်းပြီးပါပြီ။'))
        setModal(null); }
    async function submitAuth(e: FormEvent<HTMLFormElement>) { e.preventDefault(); setBusy(true); setError(''); const values=new FormData(e.currentTarget);const f={...Object.fromEntries(values),...(!authSignup?{remember:values.get('remember')==='1'}:{})}; try {
        const u = await request(authSignup ? '/register' : '/login', 'POST', f);
        history.replaceState(null, '', location.pathname);
        if (authSignup) {
            setAuthSignup(false);
            setAuthMessage('စာရင်းသွင်းပြီးပါပြီ။ ပိုင်ရှင်အတည်ပြုချက်ကို စောင့်ပါ။');
        }
        else {
            setUser(u);
            setAuthMessage('');
            setModal(null);
            changePage('dashboard');
            notify('အကောင့်ဝင်ပြီးပါပြီ။');
        }
    }
    catch (e) {
        setError((e as Error).message);
    }
    finally {
        setBusy(false);
    } }
    async function exportOrders(){try{await request('/me');const a=document.createElement('a');a.href=`${API}/orders/export?${new URLSearchParams({search:debouncedSearch,status:filter})}&${rangeQuery(range)}`;a.download='အဲအဲလေး-အော်ဒါစာရင်း.csv';a.click();notify('စာရင်းထုတ်ယူနေသည်…')}catch(e){notify((e as Error).message)}}
    function OrderTable({ compact = false }: {
        compact?: boolean;
    }) { const items = compact ? filteredOrders.slice(0, 5) : filteredOrders; return <div className="table-wrap order-table" inert={dataPending}><table><thead><tr><th>အော်ဒါ / ဝယ်သူ</th>{isOwner && <th>ကိုယ်စားလှယ်</th>}<th>ကုန်ပစ္စည်း</th><th>အရေအတွက်</th><th>စုစုပေါင်း</th><th>အခြေအနေ</th><th></th></tr></thead><tbody>{items.map(o => <tr key={o.id} onClick={() => { setError('');setDetail(o); setModal('detail'); }} tabIndex={0} onKeyDown={e => { if (e.key === 'Enter') {
        setError('');setDetail(o);
        setModal('detail');
    } }}><td><div className="customer">{o.customer}</div><span className="subtext">#{mm(o.id)} · {day(o.created_at)}</span></td>{isOwner && <td><div className="person-cell"><Avatar name={o.agent_name || 'မ'} index={o.agent_id}/><span>{o.agent_name}</span></div></td>}<td><span className="product-short">{o.product_name}</span></td><td>{mm(o.quantity)} ခု</td><td className="nowrap">{money(o.quantity * o.unit_price)}</td><td><span className={`status ${o.status}`}><i />{statusText[o.status]}</span></td><td><button className="icon-button" aria-label="အော်ဒါအသေးစိတ်" onClick={e => { e.stopPropagation(); setError('');setDetail(o); setModal('detail'); }}><ChevronRight size={17}/></button></td></tr>)}</tbody></table><div className="mobile-orders">{items.map(o => <button key={o.id} className="mobile-order" onClick={() => { setError('');setDetail(o); setModal('detail'); }}><div className="mobile-order-top"><div><strong>{o.customer}</strong><span>#{mm(o.id)} · {day(o.created_at)}</span></div><span className={`status ${o.status}`}><i />{statusText[o.status]}</span></div><div className="mobile-order-bottom"><span>{o.product_name}<small>{mm(o.quantity)} ခု{isOwner ? ` · ${o.agent_name}` : ''}</small></span><strong>{money(o.quantity * o.unit_price)}<ChevronRight size={14}/></strong></div></button>)}</div>{!items.length && <div className="empty"><ShoppingBag /><h3>အော်ဒါ မရှိသေးပါ</h3><button className="button primary" onClick={()=>openOrder()}>အော်ဒါအသစ်တင်မည်</button></div>}</div>; }
    function RankList({ full = false }: {
        full?: boolean;
    }) { return <div className="rank-list">{ranks.map((a, i) => <div className="rank-item" key={a.id}><span className={`rank-number rank-${i}`}>{(rankMeta?.from||1)+i===1?<Trophy size={17}/>:mm((rankMeta?.from||1)+i)}</span><Avatar name={a.name} index={i}/><div className="rank-name"><strong>{a.name}</strong></div><div className="rank-total"><strong>{mm(a.total)}</strong><span>ခု</span></div></div>)}{!ranks.length && <p className="empty">အရောင်းမှတ်တမ်း မရှိသေးပါ။</p>}</div>; }
    const authForm = <form onSubmit={submitAuth}>{authSignup && <label>သင့်အမည်<input name="name" required maxLength={100} autoComplete="name" placeholder="သင့်အမည် ဖြည့်ပါ"/></label>}<label>ဖုန်းနံပါတ်<input name="phone" type="tel" required pattern="09[0-9]{7,11}" autoComplete="tel" placeholder="09 ဖြင့် စသော ဖုန်းနံပါတ်"/></label><label>စကားဝှက်<div className="password-field"><input name="password" required minLength={8} type={showPassword ? 'text' : 'password'} autoComplete={authSignup ? 'new-password' : 'current-password'} placeholder="အနည်းဆုံး ၈ လုံး"/><button type="button" className="icon-button" aria-label="စကားဝှက်ပြမည်" onClick={() => setShowPassword(!showPassword)}>{showPassword ? <EyeOff size={18}/> : <Eye size={18}/>}</button></div></label>{authSignup && <p className="form-note"><Clock size={16}/>ပိုင်ရှင်အတည်ပြုပြီးမှ အကောင့်ဝင်နိုင်ပါမည်။</p>}{!authSignup&&<label className="remember-row"><input type="checkbox" name="remember" value="1"/><span>အကောင့်မှတ်ထားမည်</span></label>}{authMessage && <p className="form-note" role="status">{authMessage}</p>}{error && <p className="form-error" role="alert">{error}</p>}<button className="button primary full" disabled={busy}>{busy && <Loader2 size={17} className="spin"/>}{authSignup ? 'စာရင်းသွင်းမည်' : 'အကောင့်ဝင်မည်'}</button><div className="auth-switch">{authSignup ? 'အကောင့်ရှိပြီးပြီလား။' : 'အကောင့်မရှိသေးဘူးလား။'}<button type="button" onClick={() => { setAuthSignup(!authSignup); setError(''); setAuthMessage(''); }}>{authSignup ? 'အကောင့်ဝင်မည်' : 'စာရင်းသွင်းမည်'}</button></div></form>;
    if (authLoading)
        return <div className="auth-page"><div className="auth-loading" role="status" aria-label="စစ်ဆေးနေသည်"><Logo /><Loader2 size={24} className="spin"/></div></div>;
    if (!user)
        return <main className="auth-page"><section className="auth-card"><Logo /><h1>{authSignup ? 'စာရင်းသွင်းမည်' : 'အကောင့်ဝင်မည်'}</h1>{authForm}{!features.installed&&<button type="button" className="text-button auth-install" onClick={features.install}>ဖုန်းတွင် အက်ပ်ထည့်မည်</button>}{features.message&&<p className="form-note" role="status">{features.message}</p>}{features.error&&<p className="form-error" role="alert">{features.error}</p>}</section></main>;
    return <div className="app-shell">
 {sidebar && <div className="sidebar-scrim" onClick={() => setSidebar(false)}/>}
 <aside className={`sidebar ${sidebar ? 'open' : ''}`}><Logo /><nav>{nav.filter(n => isOwner || n.id !== 'agents').map(n => <button key={n.id} className={`nav-item ${page === n.id ? 'active' : ''}`} onClick={() => changePage(n.id)}><n.icon size={19}/><span>{n.name}</span>{n.id === 'orders' && pendingOrders > 0 && <b>{mm(pendingOrders)}</b>}{n.id === 'agents' && pendingAgents > 0 && <span className="nav-dot"/>}</button>)}</nav><div className="sidebar-bottom"><button className="nav-item" onClick={() => setModal('help')}><HelpCircle size={19}/><span>အသုံးပြုနည်း</span></button><button className={`nav-item ${page === 'settings' ? 'active' : ''}`} onClick={() => changePage('settings')}><Settings size={19}/><span>ဆက်တင်များ</span></button><div className="sidebar-profile"><Avatar name={user.name}/><div><strong>{user.name}</strong><small>{isOwner ? 'လုပ်ငန်းပိုင်ရှင်' : 'ကိုယ်စားလှယ်'}</small></div><button className="icon-button" aria-label="အကောင့်ထွက်မည်" onClick={logout}><LogOut size={17}/></button></div></div></aside>
 <div className="main-shell"><header className="topbar"><div className="breadcrumb"><button className="icon-button mobile-menu" aria-label="မီနူးဖွင့်မည်" onClick={() => setSidebar(true)}><Menu size={22}/></button><strong className="topbar-brand">အဲအဲလေး</strong></div><div className="topbar-actions"><button className="icon-button" aria-label="စာရင်းပြန်တင်မည်" disabled={loading||busy} onClick={()=>setRevision(v=>v+1)}><RefreshCw size={19}/></button><Notifications userId={user.id} request={request} onNavigate={(page,id)=>navigation.current(page,id)} onOpen={()=>setProfile(false)} features={features}/><span className="top-divider"/><div className="popover-anchor"><button className="profile-button" onClick={() => setProfile(!profile)}><Avatar name={user.name}/><span>{user.name}</span><ChevronDown size={14}/></button>{profile && <div className="popover"><strong>{user.name}</strong><button disabled={busy} onClick={logout}>အကောင့်ထွက်မည်</button></div>}</div></div></header>
 <main><div className="page-heading"><h1>{nav.find(n => n.id === page)?.name || 'ဆက်တင်များ'}</h1><div className="heading-actions">{page === 'products' && isOwner ? <button className="button primary" onClick={() => openProduct()}><Plus size={18}/>ကုန်ပစ္စည်းထည့်မည်</button> : ['dashboard', 'orders', 'products'].includes(page) && <button className="button primary" onClick={()=>openOrder()}><Plus size={18}/>အော်ဒါအသစ်တင်မည်</button>}</div></div>
 <div className="list-content" aria-busy={dataPending}>{dataPending&&<div className="list-loading" role="status"><Loader2 size={22} className="spin"/><span>စာရင်းရယူနေသည်…</span></div>}{loadError&&<div className="form-error" role="alert">{loadError}<button className="text-button" onClick={()=>setRevision(v=>v+1)}>ပြန်ကြိုးစားမည်</button></div>}<div>
 {page === 'dashboard' && <>
 <div className="section-top"><DateFilter value={range} onChange={setRange}/></div>
 <section className="stats-grid">{[{ label: 'မပို့ရသေး', value: `${mm(data.counts.orders.pending)} ခု`, icon: Truck, color: 'sand' }, { label: 'ပို့ပြီး', value: `${mm(data.summary.sent_orders)} ခု`, icon: ShoppingBag, color: 'sage' }, { label: 'အရောင်းရငွေ', value: money(revenue), icon: Wallet, color: 'rose' }, { label: 'ကော်မရှင်', value: money(commission), icon: Users, color: 'lilac' }].map(s => <article className="stat-card" key={s.label}><div className="stat-top"><span>{s.label}</span><span className={`stat-icon ${s.color}`}><s.icon size={19}/></span></div><h3>{s.value}</h3></article>)}</section>
 <section className="card orders-card"><div className="card-heading"><div className="tabs">{[['pending', 'မပို့ရသေး'], ['all', 'အားလုံး']].map(([id, label]) => <button key={id} className={filter === id ? 'selected' : ''} onClick={() => setFilter(id)}>{label}<span>{mm(data.counts.orders[id]||0)}</span></button>)}</div><button className="text-button" onClick={() => changePage('orders')}>အားလုံးကြည့်မည် <ArrowRight size={15}/></button></div><OrderTable compact/></section>
 </>}
 {page === 'orders' && <section className="card"><div className="table-toolbar"><div className="tabs">{[['all', 'အားလုံး'], ['pending', 'မပို့ရသေး'], ['sent', 'ပို့ပြီး'], ['cancelled', 'ပယ်ဖျက်ပြီး']].map(([id, label]) => <button className={filter === id ? 'selected' : ''} onClick={() => setFilter(id)} key={id}>{label}<span>{mm(data.counts.orders[id]||0)}</span></button>)}</div><button className="button secondary" onClick={exportOrders}><Download size={16}/>စာရင်းထုတ်ယူမည်</button></div><div className="order-date-filter"><DateFilter value={range} onChange={setRange}/></div><div className="search-row"><div className="search-field"><Search size={17}/><input aria-label="အော်ဒါရှာဖွေရန်" placeholder="ဝယ်သူအမည်၊ ဖုန်း သို့မဟုတ် အော်ဒါအမှတ်ဖြင့် ရှာရန်…" value={search} onChange={e => setSearch(e.target.value)}/>{search && <button className="icon-button" onClick={() => setSearch('')} aria-label="ရှာဖွေမှုရှင်းရန်"><X size={15}/></button>}</div><span className="subtext">အော်ဒါ {mm(data.pagination.orders?.total||0)} ခု</span></div><OrderTable />{pager('orders')}</section>}
 {page === 'products' && <><div className="products-grid" inert={dataPending}>{data.products.filter(p => isOwner || p.active).map(p => <article className="card product-card" key={p.id}><div className="product-image"><img src={p.image || '/products/cream.jpg'} alt={p.name} loading="lazy" decoding="async"/><span className={`product-badge ${!p.active ? 'inactive' : ''}`}>{p.active ? 'ရောင်းချနေသည်' : 'ရောင်းချမှု ရပ်ထားသည်'}</span>{isOwner && <button className="icon-button product-edit" onClick={() => openProduct(p)} aria-label="ကုန်ပစ္စည်းပြင်မည်"><Pencil size={17}/></button>}</div><div className="product-body"><h2>{p.name}</h2><details className="product-description"><summary>အသေးစိတ်</summary><p>{p.description}</p></details><div className="product-price"><strong>{money(p.price)}</strong><span>တစ်ခုဈေးနှုန်း</span></div><div className="product-meta"><div><Wallet size={17}/><span>တစ်ခု ကော်မရှင်<strong>{money(p.commission)}</strong></span></div><div><Package size={17}/><span>လက်ကျန်<strong>{mm(p.stock)} ခု</strong></span></div></div><button className="button primary full" disabled={!p.active || p.stock === 0} onClick={() => { openOrder(p); }}><Plus size={17}/>အော်ဒါတင်မည်</button>{isOwner && <button className="text-button centered" disabled={busy} onClick={() => mutate(`/products/${p.id}`, 'PUT', { ...p, active: !p.active }, 'ရောင်းချမှု အခြေအနေ ပြောင်းလဲပြီးပါပြီ။')}>{p.active ? 'ရောင်းချမှု ခေတ္တရပ်မည်' : 'ပြန်လည်ရောင်းချမည်'}</button>}</div></article>)}</div>{pager('products')}</>}
 {page === 'agents' && isOwner && <><div className="info-banner"><Users size={22}/><div><strong>ကိုယ်စားလှယ် ဖိတ်ခေါ်ရန်</strong></div><button className="button secondary" onClick={async () => { try {
        await navigator.clipboard.writeText(`${location.origin}/?register=1`);
        notify('စာရင်းသွင်းလင့်ခ်ကို ကူးယူပြီးပါပြီ။');
    }
    catch {
        notify(`စာရင်းသွင်းလင့်ခ် — ${location.origin}/?register=1`);
    } }}><Copy size={16}/>လင့်ခ်ကူးမည်</button></div><section className="card"><div className="table-toolbar"><div className="tabs">{[['approved', 'အတည်ပြုပြီး'], ['pending', 'အတည်ပြုရန်'], ['rejected', 'ငြင်းပယ်ပြီး']].map(([id, label]) => <button key={id} className={agentTab === id ? 'selected' : ''} onClick={() => setAgentTab(id)}>{label}<span>{mm(data.counts.agents[id]||0)}</span></button>)}</div></div><div className="search-row"><div className="search-field"><Search size={17}/><input aria-label="ကိုယ်စားလှယ်ရှာရန်" value={search} onChange={e=>setSearch(e.target.value)} placeholder="အမည် သို့မဟုတ် ဖုန်းဖြင့် ရှာရန်"/></div></div><div className="agent-list" inert={dataPending}>{data.users.filter(u => u.role === 'agent' && u.status === agentTab).map((a, i) => <div className="agent-row" key={a.id}><Avatar name={a.name} index={i}/><div className="agent-info"><strong>{a.name}</strong><span>{a.phone}</span></div><div className="agent-sales"><span>ရောင်းချပြီး အရေအတွက်</span><strong>{mm(a.sold||0)} ခု</strong></div>{agentTab === 'pending' ? <div className="row-actions"><button className="button secondary" disabled={busy} onClick={() => mutate(`/agents/${a.id}`, 'PATCH', { status: 'rejected' }, 'လျှောက်လွှာကို ငြင်းပယ်ပြီးပါပြီ။')}><X size={15}/>ငြင်းပယ်မည်</button><button className="button primary" disabled={busy} onClick={() => mutate(`/agents/${a.id}`, 'PATCH', { status: 'approved' }, 'ကိုယ်စားလှယ်ကို အတည်ပြုပြီးပါပြီ။')}><Check size={15}/>အတည်ပြုမည်</button></div> : <span className={`status ${agentTab === 'approved' ? 'sent' : 'cancelled'}`}><i />{agentTab === 'approved' ? 'အတည်ပြုပြီး' : 'ငြင်းပယ်ပြီး'}</span>}</div>)}{!data.users.some(u => u.role === 'agent' && u.status === agentTab) && <div className="empty"><Users /><p>ဤစာရင်းတွင် ကိုယ်စားလှယ် မရှိသေးပါ။</p></div>}</div>{pager('users')}</section></>}
 {page === 'earnings' && <><div className="stats-grid earnings-stats">{[{ label: 'ရရှိပြီး ကော်မရှင်', value:data.summary.earned, icon: Wallet }, { label: 'ငွေရှင်းပြီး စုစုပေါင်း', value:data.summary.paid, icon: CheckCircle2 }, { label: 'ငွေရှင်းရန် ကျန်ရှိ', value:data.summary.balance, icon: Clock }].map(s => <article className="stat-card" key={s.label}><div className="stat-top"><span>{s.label}</span><span className="stat-icon lilac"><s.icon size={19}/></span></div><h3>{money(s.value)}</h3></article>)}</div>{isOwner && <section className="card"><div className="card-heading"><div><h2>ကိုယ်စားလှယ် ကော်မရှင်များ</h2></div></div><div className="search-row"><div className="search-field"><Search size={17}/><input aria-label="ကိုယ်စားလှယ်ရှာရန်" value={search} onChange={e=>setSearch(e.target.value)} placeholder="အမည် သို့မဟုတ် ဖုန်းဖြင့် ရှာရန်"/></div></div><div className="table-wrap" inert={dataPending}><table><thead><tr><th>ကိုယ်စားလှယ်</th><th>ရရှိပြီး</th><th>ငွေရှင်းပြီး</th><th>ကျန်ရှိငွေ</th><th></th></tr></thead><tbody>{data.users.map((a, i) => { const b = balances(data, a.id); return <tr key={a.id}><td><div className="person-cell"><Avatar name={a.name} index={i}/>{a.name}</div></td><td>{money(b.earned)}</td><td>{money(b.paid)}</td><td><strong>{money(b.balance)}</strong></td><td><button className="button secondary" disabled={b.balance <= 0} onClick={() => { setPayoutAgent(a); setRequestKey(requestId());setError(''); setModal('payout'); }}>ငွေရှင်းမှတ်တမ်းထည့်မည်</button></td></tr>; })}</tbody></table></div>{pager('users')}</section>}<section className="card section-space"><div className="card-heading"><div><h2>ငွေရှင်းမှတ်တမ်း</h2></div><DateFilter value={range} onChange={setRange}/></div><div className="table-wrap" inert={dataPending}><table><thead><tr><th>ရက်စွဲ</th><th>ကိုယ်စားလှယ်</th><th>ပမာဏ</th><th>ငွေလွှဲအမှတ် / မှတ်ချက်</th><th>အခြေအနေ</th></tr></thead><tbody>{data.payouts.filter(p => isOwner || p.agent_id === user.id).map(p => <tr key={p.id}><td>{day(p.created_at)}</td><td>{p.agent_name||user.name}</td><td>{money(p.amount)}</td><td>{p.reference}</td><td><span className="status sent"><i />ငွေရှင်းပြီး</span></td></tr>)}</tbody></table>{!data.payouts.some(p => isOwner || p.agent_id === user.id) && <div className="empty"><Wallet /><p>ငွေရှင်းမှတ်တမ်း မရှိသေးပါ။</p></div>}</div>{pager('payouts')}</section></>}
 {page === 'leaderboard' && <><div className="section-top"><span className="ranking-note">ပို့ပြီးအရေအတွက်အလိုက်</span><DateFilter value={range} onChange={setRange}/></div><section className="card full-leaderboard">{rankLoading ? <div className="empty" role="status">စာရင်းရယူနေသည်…</div> : rankError ? <p className="form-error" role="alert">{rankError}</p> : <RankList full/>}{pager('rankings',rankMeta)}</section></>}
 {page === 'settings' && <section className="card settings-card"><DeviceFeatures features={features}/><dl><div><dt>အမည်</dt><dd>{user.name}</dd></div><div><dt>ဖုန်းနံပါတ်</dt><dd>{user.phone}</dd></div><div><dt>အကောင့်အမျိုးအစား</dt><dd>{isOwner ? 'လုပ်ငန်းပိုင်ရှင်' : 'ကိုယ်စားလှယ်'}</dd></div><div><dt>ငွေကြေး</dt><dd>မြန်မာကျပ်</dd></div></dl><button className="button secondary" disabled={busy} onClick={logout}><LogOut size={16}/>အကောင့်ထွက်မည်</button><button className="button secondary" onClick={()=>{setPasswordForm(!passwordForm);setError('')}}>စကားဝှက်ပြောင်းမည်</button>{passwordForm&&<form className="password-change" onSubmit={async e=>{e.preventDefault();const form=e.currentTarget;const body=Object.fromEntries(new FormData(form));if(await mutate('/password','PUT',body,'စကားဝှက် ပြောင်းပြီးပါပြီ။')){form.reset();setPasswordForm(false);window.dispatchEvent(new Event('aeae:push-changed'))}}}><label>လက်ရှိစကားဝှက်<input type="password" name="current_password" autoComplete="current-password" required/></label><label>စကားဝှက်အသစ်<input type="password" name="password" autoComplete="new-password" minLength={10} maxLength={128} required/></label><label>စကားဝှက်အသစ် ထပ်ဖြည့်ပါ<input type="password" name="password_confirmation" autoComplete="new-password" minLength={10} maxLength={128} required/></label>{error&&<p className="form-error" role="alert">{error}</p>}<button className="button primary" disabled={busy}>သိမ်းဆည်းမည်</button></form>}</section>}

 </div></div></main></div>
 {toast && <div className="toast" role="status"><CheckCircle2 size={19}/>{toast}<button aria-label="ပိတ်မည်" onClick={() => setToast('')}><X size={16}/></button></div>}
 {modal === 'order' && <Modal busy={busy} title="အော်ဒါအသစ်တင်မည်" onClose={() => setModal(null)}><form onSubmit={submitOrder}>{isOwner&&<OptionSelect<User> type="agents" label="ကိုယ်စားလှယ်" name="agent_id" value={chosenAgent} onChange={setChosenAgent}/>}<OptionSelect<Product> type="products" reloadKey={optionRevision} label="ကုန်ပစ္စည်း" name="product_id" value={chosenProduct} onChange={setChosenProduct} initial={chosenProduct||undefined}/><div className="form-row"><label>အရေအတွက်<input type="number" name="quantity" min="1" max={pSelected?.stock || 1} step="1" required value={quantity} onChange={e => setQuantity(Number(e.target.value))}/></label><label>ဝယ်သူအမည်<input name="customer" required maxLength={100} placeholder="မခင်သီတာ"/></label></div><label>ဖုန်းနံပါတ်<input name="phone" type="tel" required pattern="[0-9+\s\-]{7,20}" maxLength={20} placeholder="၀၉ အစား 09 ဖြင့် ဖြည့်ပါ"/></label><label>ပို့ဆောင်ရမည့် လိပ်စာ<textarea name="address" required maxLength={1000} rows={3} placeholder="အိမ်အမှတ်၊ လမ်း၊ ရပ်ကွက်၊ မြို့နယ်"/></label><div className="order-summary"><div><span>စုစုပေါင်း ကျသင့်ငွေ</span><strong>{money((pSelected?.price || 0) * quantity)}</strong></div><div><span>ပို့ပြီးနောက် ရရှိမည့် ကော်မရှင်</span><strong>{money((pSelected?.commission || 0) * quantity)}</strong></div></div>{error && <p className="form-error" role="alert">{error}</p>}<button className="button primary full" disabled={busy || !pSelected || (isOwner&&!chosenAgent)}>{busy ? <Loader2 className="spin" size={18}/> : <Check size={18}/>}အော်ဒါ အတည်ပြုမည်</button></form></Modal>}
 {modal === 'product' && <Modal busy={busy||imageReading} title={editProduct ? 'ကုန်ပစ္စည်း ပြင်ဆင်မည်' : 'ကုန်ပစ္စည်းအသစ် ထည့်မည်'} onClose={() => setModal(null)}><form onSubmit={submitProduct}><label>ကုန်ပစ္စည်းအမည်<input name="name" required maxLength={150} defaultValue={editProduct?.name}/></label><label>အကြောင်းအရာ<textarea name="description" rows={3} required maxLength={2000} defaultValue={editProduct?.description}/></label><div className="form-row"><label>ရောင်းဈေး (ကျပ်)<input type="number" min="1" max="100000000" step="1" name="price" required defaultValue={editProduct?.price}/></label><label>တစ်ခု ကော်မရှင် (ကျပ်)<input type="number" min="0" step="1" name="commission" required defaultValue={editProduct?.commission}/></label></div><label>လက်ကျန် အရေအတွက်<input type="number" min="0" max="1000000" step="1" name="stock" required defaultValue={editProduct?.stock}/></label><label>ကုန်ပစ္စည်းဓာတ်ပုံ<input type="file" accept="image/jpeg,image/png,image/webp" onChange={e => { const file = e.target.files?.[0]; if (!file)
        return; if (file.size > 2 * 1024 * 1024) { e.target.value='';
        setFileError('ဓာတ်ပုံသည် ၂ မီဂါဘိုက်ထက် မကြီးရပါ။');setError('ဓာတ်ပုံသည် ၂ မီဂါဘိုက်ထက် မကြီးရပါ။');
        return;
    } setImageReading(true);const reader = new FileReader(); reader.onload = () => { const input = document.querySelector<HTMLInputElement>('#product-image-value'); if (input)
        input.value = String(reader.result); setError('');setFileError('');setImageReading(false); }; reader.onerror=()=>{setImageReading(false);setFileError('ဓာတ်ပုံဖတ်၍ မရပါ။')};reader.readAsDataURL(file); }}/><input id="product-image-value" name="image" type="hidden" defaultValue={editProduct?.image || '/products/cream.jpg'}/><small>ဓာတ်ပုံအရွယ်အစား အများဆုံး ၂ မီဂါဘိုက်။</small></label>{error && <p className="form-error" role="alert">{error}</p>}<button className="button primary full" disabled={busy||imageReading}>သိမ်းဆည်းမည်</button></form></Modal>}

 {modal === 'payout' && payoutAgent && <Modal busy={busy} title="ကော်မရှင် ငွေရှင်းမှတ်တမ်း" onClose={() => setModal(null)}><form onSubmit={async (e) => { e.preventDefault(); const f = new FormData(e.currentTarget), amount = Number(f.get('amount')), reference = String(f.get('reference')); if (amount > (payoutAgent.balance||0)) {
        setError('ကျန်ရှိငွေထက် ပိုမိုငွေရှင်း၍ မရပါ။');
        return;
    } if (await mutate('/payouts', 'POST', {request_key:requestKey,agent_id:payoutAgent.id,amount,reference}, 'ငွေရှင်းမှတ်တမ်း သိမ်းဆည်းပြီးပါပြီ။'))
        setModal(null); }}><p className="form-intro">{payoutAgent.name} အတွက် ငွေရှင်းရန် ကျန်ရှိငွေ <strong>{money(payoutAgent.balance||0)}</strong></p><label>ပေးချေပြီး ပမာဏ (ကျပ်)<input name="amount" type="number" step="1" min="1" max={payoutAgent.balance||0} required defaultValue={payoutAgent.balance||0}/></label><label>ငွေလွှဲအမှတ် / မှတ်ချက်<input name="reference" required maxLength={250} placeholder="ငွေလွှဲအမှတ် သို့မဟုတ် ငွေသားပေးချေမှု မှတ်ချက်"/></label><p className="form-note">ငွေပေးချေပြီးမှ မှတ်တမ်းတင်ပါ။</p>{error && <p className="form-error" role="alert">{error}</p>}<button className="button primary full" disabled={busy}>ပေးချေပြီးအဖြစ် မှတ်တမ်းတင်မည်</button></form></Modal>}
 {modal === 'detail' && detail && <Modal busy={busy} title={`အော်ဒါ #${mm(detail.id)}`} onClose={() => setModal(null)}><div className="order-detail">{error&&<p className="form-error" role="alert">{error}</p>}<span className={`status ${detail.status}`}><i />{statusText[detail.status]}</span><dl>{[['ဝယ်သူအမည်', detail.customer], ['ဖုန်းနံပါတ်', detail.phone], ['ပို့ဆောင်ရန်လိပ်စာ', detail.address], ['ကိုယ်စားလှယ်', detail.agent_name||user.name], ['ကုန်ပစ္စည်း', detail.product_name], ['အရေအတွက်', `${mm(detail.quantity)} ခု`], ['အော်ဒါရက်စွဲ', day(detail.created_at)], ['စုစုပေါင်း', money(detail.quantity * detail.unit_price)], ['ကော်မရှင်', money(detail.quantity * detail.unit_commission)]].map(([k, v]) => <div key={k}><dt>{k}</dt><dd>{v}</dd></div>)}</dl>{isOwner && detail.status === 'pending' && <><div className="form-row"><button className="button secondary" disabled={busy} onClick={() => updateStatus(detail, 'cancelled')}><X size={16}/>ပယ်ဖျက်မည်</button><button className="button primary" disabled={busy} onClick={() => updateStatus(detail, 'sent')}><Truck size={17}/>ပို့ပြီး မှတ်သားမည်</button></div></>}</div></Modal>}
 {modal === 'help' && <Modal busy={busy} title="အဲအဲလေး အသုံးပြုနည်း" onClose={() => setModal(null)}><div className="help-content">{[['၁', 'ကိုယ်စားလှယ်အဖြစ် စာရင်းသွင်းပါ', 'ဖုန်းနံပါတ်၊ အမည်နဲ့ စကားဝှက်ဖြည့်ပြီး ပိုင်ရှင်အတည်ပြုချက်ကို စောင့်ပါ။'], ['၂', 'အော်ဒါတင်ပါ', 'ပစ္စည်းရွေးပြီး ဝယ်သူအမည်၊ ဖုန်းနဲ့ လိပ်စာကို ဖြည့်ပါ။'], ['၃', 'ပို့ဆောင်မှုကို ကြည့်ပါ', 'ပိုင်ရှင်က ပစ္စည်းပို့ပြီးတဲ့အခါ အော်ဒါအခြေအနေ ပြောင်းလဲပေးပါမယ်။'], ['၄', 'ကော်မရှင်ကို စစ်ဆေးပါ', 'ပို့ပြီးအော်ဒါတွေအတွက် ကော်မရှင်ရရှိပြီး ငွေရှင်းမှတ်တမ်းကို ကြည့်နိုင်ပါတယ်။']].map(([n, t, d]) => <div key={n}><span>{n}</span><section><h3>{t}</h3><p>{d}</p></section></div>)}</div></Modal>}
 <nav className="bottom-nav" aria-label="အဓိက မီနူး">{nav.filter(n => ['dashboard', 'orders', 'products', 'earnings'].includes(n.id)).map(n => <button key={n.id} className={page === n.id ? 'active' : ''} onClick={() => changePage(n.id)}><n.icon size={20}/><span>{n.name}</span></button>)}<button onClick={() => setSidebar(true)}><Menu size={20}/><span>အခြား</span></button></nav>

 </div>;
}
