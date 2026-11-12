const {test}=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');
function worker({recipient=7,allowed=true,endpoint='https://fcm.googleapis.com/fcm/send/private-device',fetchFails=false}={}){
 const handlers={},shown=[],calls=[];
 const indexedDB={open(){const open={};queueMicrotask(()=>{open.result={close(){},transaction(){return {objectStore(){return {get(){const request={result:recipient};queueMicrotask(()=>request.onsuccess());return request}}}}}};open.onsuccess()});return open}};
 const self={location:{origin:'https://example.com'},addEventListener(type,handler){handlers[type]=handler},registration:{pushManager:{getSubscription:async()=>endpoint?{endpoint}:null},showNotification:async(title,options)=>shown.push({title,...options})},clients:{matchAll:async()=>[]}};
 vm.runInNewContext(fs.readFileSync('public/sw.js','utf8'),{self,indexedDB,URL,URLSearchParams,Promise,Number,String,fetch:async(url,options)=>{calls.push({url,options});if(fetchFails)throw Error('offline');return{ok:true,json:async()=>({allowed})}}});
 return{shown,calls,async push(payload={id:1,recipient_id:7,title:'အော်ဒါအသစ်',body:'FEG Rice Shampoo · ၂၅ ခု · မသဇင်',destination:'orders',resource_id:1}){let work;handlers.push({data:{json:()=>payload},waitUntil(promise){work=promise}});await work}};
}
test('background push displays with no window and no login-cookie request',async()=>{const w=worker();await w.push();assert.equal(w.shown.length,1);assert.match(w.shown[0].body,/FEG Rice Shampoo/);assert.equal(w.calls[0].url,'/api/push/verify');assert.equal(w.calls[0].options.credentials,'omit');assert.deepEqual(JSON.parse(w.calls[0].options.body),{endpoint:'https://fcm.googleapis.com/fcm/send/private-device',recipient_id:7});});
test('another account on a shared device cannot see the previous account notice',async()=>{const w=worker({recipient:8});await w.push();assert.equal(w.shown.length,0);assert.equal(w.calls.length,0);});
test('explicitly logged-out device has no account binding',async()=>{const w=worker({recipient:null});await w.push();assert.equal(w.shown.length,0);});
test('revoked or suspended device cannot display a queued notice',async()=>{const w=worker({allowed:false});await w.push();assert.equal(w.shown.length,0);});
test('no subscription or failed verification fails closed',async()=>{for(const options of [{endpoint:null},{fetchFails:true}]){const w=worker(options);await w.push();assert.equal(w.shown.length,0);}});
test('malformed payload never triggers a device lookup',async()=>{const w=worker();await w.push({id:'1',recipient_id:7});assert.equal(w.shown.length,0);assert.equal(w.calls.length,0);});
