export type ApiRequest=(path:string,method?:string,body?:unknown,signal?:AbortSignal)=>Promise<any>;
type InstallEvent=Event&{prompt:()=>Promise<void>;userChoice:Promise<{outcome:'accepted'|'dismissed'}>};
let installPrompt:InstallEvent|null=null,registration:Promise<ServiceWorkerRegistration>|null=null;
export const isInstalled=()=>window.matchMedia('(display-mode: standalone)').matches||!!(navigator as Navigator&{standalone?:boolean}).standalone;
export const isIos=()=>/iPhone|iPad|iPod/.test(navigator.userAgent)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1);
export function initPwa(){
 window.addEventListener('beforeinstallprompt',event=>{event.preventDefault();installPrompt=event as InstallEvent;window.dispatchEvent(new Event('aeae:install-changed'))});
 window.addEventListener('appinstalled',()=>{installPrompt=null;window.dispatchEvent(new Event('aeae:install-changed'))});
 if(import.meta.env.PROD&&'serviceWorker' in navigator){registerWorker().then(r=>r.update()).catch(()=>{});}
}
export const canPromptInstall=()=>!!installPrompt;
export async function installApp(){const prompt=installPrompt;if(!prompt)return false;await prompt.prompt();const choice=await prompt.userChoice;installPrompt=null;window.dispatchEvent(new Event('aeae:install-changed'));return choice.outcome==='accepted';}
export function registerWorker(){
 if(!window.isSecureContext||!('serviceWorker' in navigator))return Promise.reject(new Error('ဤဘရောက်ဇာတွင် အက်ပ်ထည့်သွင်းမှု မရနိုင်ပါ။'));
 if(!registration)registration=navigator.serviceWorker.register('/sw.js',{scope:'/',updateViaCache:'none'}).catch(error=>{registration=null;throw error});
 return registration;
}
async function readyWorker(){await registerWorker();return new Promise<ServiceWorkerRegistration>((resolve,reject)=>{const timer=setTimeout(()=>reject(new Error('အက်ပ်ဝန်ဆောင်မှု အသင့်မဖြစ်သေးပါ။ ပြန်ကြိုးစားပါ။')),15000);navigator.serviceWorker.ready.then(r=>{clearTimeout(timer);resolve(r)})});}
export function supportsPush(){return window.isSecureContext&&'serviceWorker' in navigator&&'PushManager' in window&&'Notification' in window;}
export async function currentPushEndpoint(){try{if(!('serviceWorker' in navigator))return null;const r=await navigator.serviceWorker.getRegistration('/');return(await r?.pushManager.getSubscription())?.endpoint||null}catch{return null}}
export async function pushEnabled(request:ApiRequest){const endpoint=await currentPushEndpoint();if(!endpoint)return false;return(await request('/push/status','POST',{endpoint})).enabled as boolean;}
function decodeKey(value:string){const base64=value.replace(/-/g,'+').replace(/_/g,'/');const raw=atob(base64+'='.repeat((4-base64.length%4)%4));return Uint8Array.from(raw,c=>c.charCodeAt(0));}
export async function enablePush(request:ApiRequest){
 if(isIos()&&!isInstalled())throw new Error('အသိပေးချက်အတွက် အက်ပ်ကို ပင်မမျက်နှာပြင်တွင် အရင်ထည့်ပါ။');
 if(!supportsPush())throw new Error('ဤဘရောက်ဇာတွင် ဖုန်းအသိပေးချက် မရနိုင်ပါ။');
 // Permission is requested only in direct response to the user's button tap.
 let permission=Notification.permission;if(permission==='default')permission=await Notification.requestPermission();
 if(permission!=='granted')throw new Error(permission==='denied'?'ဘရောက်ဇာဆက်တင်တွင် အသိပေးချက်ကို ခွင့်ပြုပေးပါ။':'အသိပေးချက် ခွင့်မပြုရသေးပါ။');
 const settings=await request('/push/settings');if(!settings.configured)throw new Error('ဖုန်းအသိပေးချက်ကို ပိုင်ရှင်က စတင်ဖွင့်ပေးရန် လိုပါသည်။');
 const worker=await readyWorker();let subscription=await worker.pushManager.getSubscription();
 const desired=decodeKey(settings.public_key);const existing=subscription?.options.applicationServerKey;
 if(subscription&&existing&&Array.from(new Uint8Array(existing)).join(',')!==Array.from(desired).join(',')){await subscription.unsubscribe();subscription=null;}
 try{subscription??=await worker.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:desired});}catch{throw new Error('ဖုန်းအသိပေးချက် ချိတ်ဆက်၍ မရပါ။ အင်တာနက်စစ်ပြီး ပြန်ကြိုးစားပါ။');}
 try{await request('/push/subscribe','POST',subscription.toJSON())}catch(error){await subscription.unsubscribe().catch(()=>{});throw error;}
 await request('/push/test','POST').catch(()=>{});window.dispatchEvent(new Event('aeae:push-changed'));
}
export async function disablePush(request:ApiRequest){const r=await navigator.serviceWorker.getRegistration('/');const sub=await r?.pushManager.getSubscription();if(sub){await request('/push/subscribe','DELETE',{endpoint:sub.endpoint});await sub.unsubscribe();}r?.active?.postMessage({type:'LOGOUT'});window.dispatchEvent(new Event('aeae:push-changed'));}
export async function clearDeviceAfterLogout(){try{const r=await navigator.serviceWorker.getRegistration('/');r?.active?.postMessage({type:'LOGOUT'});await(await r?.pushManager.getSubscription())?.unsubscribe();}catch{}window.dispatchEvent(new Event('aeae:push-changed'));}
