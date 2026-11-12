// Only the selected account id is persisted; no orders, passwords or customer data.
let desiredRecipient:number|null|undefined;
export function setPushRecipient(recipient:number|null):Promise<void>{
 desiredRecipient=recipient;
 return new Promise((resolve,reject)=>{
  const request=indexedDB.open('aeae-push-binding',1);
  request.onupgradeneeded=()=>request.result.createObjectStore('settings');
  request.onerror=()=>reject(request.error);
  request.onsuccess=()=>{
   const db=request.result;if(desiredRecipient!==recipient){db.close();resolve();return;}
   const tx=db.transaction('settings','readwrite');const store=tx.objectStore('settings');
   if(recipient===null)store.delete('recipient');else store.put(recipient,'recipient');
   tx.oncomplete=()=>{db.close();resolve()};tx.onerror=()=>{db.close();reject(tx.error)};tx.onabort=()=>{db.close();reject(tx.error)};
  };
 });
}
