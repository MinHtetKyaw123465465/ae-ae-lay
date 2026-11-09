import {cp,mkdir,readdir,rm,readFile,writeFile} from 'node:fs/promises';
import {resolve} from 'node:path';
const root=resolve(import.meta.dirname,'..');
const target=resolve(root,'backend/public');
const assets=await readdir(resolve(root,'dist/assets'));
const version=assets.join('-').replace(/[^a-zA-Z0-9_-]/g,'');
const sw=(await readFile(resolve(root,'dist/sw.js'),'utf8')).replace('__BUILD_ID__',version).replace('const STATIC_ASSETS = [];','const STATIC_ASSETS = '+JSON.stringify(assets.map(file=>'/assets/'+file))+';');
await writeFile(resolve(root,'dist/sw.js'),sw);
await mkdir(target,{recursive:true});
await cp(resolve(root,'dist'),target,{recursive:true});
const currentAssets=new Set(await readdir(resolve(root,'dist/assets')));
for(const file of await readdir(resolve(target,'assets'))){if(!currentAssets.has(file))await rm(resolve(target,'assets',file),{recursive:true,force:true});}
console.log('Laravel public assets ready.');
