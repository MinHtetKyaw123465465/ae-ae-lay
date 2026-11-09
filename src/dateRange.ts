export type Preset='today'|'yesterday'|'last7'|'week'|'month'|'lastMonth'|'year'|'all'|'custom';
export type DateRange={preset:Preset;from:string;to:string};
export const businessDate=(value:string|Date=new Date())=>{const ms=new Date(value).getTime();return Number.isFinite(ms)?new Date(ms+390*60000).toISOString().slice(0,10):''};
const shift=(date:string,days:number)=>new Date(Date.parse(date+'T00:00:00Z')+days*86400000).toISOString().slice(0,10);
export function presetRange(preset:Preset,now:Date=new Date()):DateRange{
 const today=businessDate(now),[year,month]=today.split('-').map(Number);let from=today,to=today;
 switch(preset){
  case 'yesterday':from=to=shift(today,-1);break;
  case 'last7':from=shift(today,-6);break;
  case 'week':from=shift(today,-((new Date(today+'T00:00:00Z').getUTCDay()+6)%7));break;
  case 'month':from=`${today.slice(0,7)}-01`;break;
  case 'lastMonth':to=shift(`${today.slice(0,7)}-01`,-1);from=`${to.slice(0,7)}-01`;break;
  case 'year':from=`${year}-01-01`;break;
  case 'all':from=to='';break;
  case 'custom':from=`${year}-${String(month).padStart(2,'0')}-01`;break;
 }
 return{preset,from,to};
}
export function inRange(value:string,range:DateRange){const date=businessDate(value);return !!date&&(!range.from||date>=range.from)&&(!range.to||date<=range.to);}
export function rangeQuery(range:DateRange){const query=new URLSearchParams();if(range.from)query.set('from',range.from);if(range.to)query.set('to',range.to);return query.toString();}
