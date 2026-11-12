/* Cache public application assets only. Authenticated API data is never cached. */
const BUILD_ID = 'index-BHzcWbbocss-index-COH7usOQjs';
const CACHE = 'aeae-static-' + BUILD_ID;
const STATIC_ASSETS = ["/assets/index-BHzcWbbo.css","/assets/index-COH7usOQ.js"];
const CORE = ['/offline.html','/offline.css','/manifest.webmanifest','/icons/aeae-180.png','/icons/aeae-192.png','/icons/aeae-512.png','/fonts/fonts.css','/fonts/noto-myanmar-400.ttf'];
self.addEventListener('install',event=>{event.waitUntil(caches.open(CACHE).then(cache=>cache.addAll([...CORE,...STATIC_ASSETS])).then(()=>self.skipWaiting()));});
self.addEventListener('activate',event=>{event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k.startsWith('aeae-static-')&&k!==CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim()));});
self.addEventListener('fetch',event=>{
 const url=new URL(event.request.url);
 if(url.origin!==self.location.origin||event.request.method!=='GET'||url.pathname.startsWith('/api/'))return;
 if(event.request.mode==='navigate'){
  event.respondWith(fetch(event.request).catch(()=>caches.match('/offline.html')));return;
 }
 if(CORE.includes(url.pathname)||STATIC_ASSETS.includes(url.pathname))event.respondWith(caches.match(event.request).then(cached=>cached||fetch(event.request)));
});
self.addEventListener('message',event=>{
 if(event.data?.type==='LOGOUT')event.waitUntil(self.registration.getNotifications().then(rows=>rows.forEach(row=>row.close())));
});
function boundRecipient(){return new Promise((resolve,reject)=>{
 const open=indexedDB.open('aeae-push-binding',1);open.onupgradeneeded=()=>open.result.createObjectStore('settings');open.onerror=()=>reject(open.error);
 open.onsuccess=()=>{const db=open.result;const request=db.transaction('settings','readonly').objectStore('settings').get('recipient');request.onsuccess=()=>{db.close();resolve(request.result)};request.onerror=()=>{db.close();reject(request.error)}};
});}
self.addEventListener('push',event=>{event.waitUntil((async()=>{
 let data;try{data=event.data?.json();}catch{return;}
 if(!data||!Number.isSafeInteger(data.id)||!Number.isSafeInteger(data.recipient_id))return;
 // Bind to the chosen account and verify device revocation without session cookies.
 try{
  if(await boundRecipient()!==data.recipient_id)return;
  const subscription=await self.registration.pushManager.getSubscription();if(!subscription)return;
  const response=await fetch('/api/push/verify',{method:'POST',credentials:'omit',cache:'no-store',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify({endpoint:subscription.endpoint,recipient_id:data.recipient_id})});
  if(!response.ok||!(await response.json()).allowed)return;
 }catch{return;}
 const allowed=['dashboard','orders','agents','earnings'];const destination=allowed.includes(data.destination)?data.destination:'dashboard';
 await self.registration.showNotification(String(data.title||'Ae Ae Business'),{body:String(data.body||'အသိပေးချက်အသစ် ရှိသည်။'),icon:'/icons/aeae-192.png',badge:'/icons/aeae-192.png',tag:'aeae-'+data.id,renotify:false,data:{destination,resource_id:Number(data.resource_id)||null,id:data.id}});
 const clients=await self.clients.matchAll({type:'window',includeUncontrolled:true});clients.forEach(client=>client.postMessage({type:'NOTIFICATION_RECEIVED'}));
})());});
self.addEventListener('notificationclick',event=>{event.notification.close();event.waitUntil((async()=>{
 const data=event.notification.data||{};const allowed=['dashboard','orders','agents','earnings'];const destination=allowed.includes(data.destination)?data.destination:'dashboard';const resource=Number(data.resource_id);const query=new URLSearchParams({page:destination});if(Number.isSafeInteger(resource)&&resource>0&&destination==='orders')query.set('order',String(resource));
 const windows=await self.clients.matchAll({type:'window',includeUncontrolled:true});const client=windows.find(c=>new URL(c.url).origin===self.location.origin);
 if(client){await client.focus();client.postMessage({type:'NAVIGATE_NOTIFICATION',destination,resource_id:resource||null});}else await self.clients.openWindow('/?'+query);
})());});
