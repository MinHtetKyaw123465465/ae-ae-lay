export type User={id:number;name:string;phone:string;role:'owner'|'agent';status:'pending'|'approved'|'rejected';created_at?:string;earned?:number;paid?:number;balance?:number;sold?:number};
export type Product={id:number;name:string;description:string;price:number;commission:number;stock:number;image:string;active:boolean;lock_version:number};
export type Order={id:number;agent_id:number;product_id:number;customer:string;phone:string;address:string;quantity:number;unit_price:number;unit_commission:number;status:'pending'|'sent'|'cancelled';created_at:string;sent_at?:string|null;product_name?:string;agent_name?:string};
export type Payout={id:number;agent_id:number;amount:number;reference:string;created_at:string;agent_name?:string};
export type PageMeta={current_page:number;last_page:number;per_page:number;total:number;from:number|null;to:number|null};
export type ListName='orders'|'users'|'products'|'payouts'|'rankings';
export type Data={users:User[];products:Product[];orders:Order[];payouts:Payout[];pagination:Partial<Record<ListName,PageMeta>>;counts:{pending_orders:number;pending_agents:number;orders:Record<string,number>;agents:Record<string,number>};summary:{revenue:number;commission:number;sent_orders:number;earned:number;paid:number;balance:number}};
export const emptyData=():Data=>({users:[],products:[],orders:[],payouts:[],pagination:{},counts:{pending_orders:0,pending_agents:0,orders:{all:0,pending:0,sent:0,cancelled:0},agents:{approved:0,pending:0,rejected:0}},summary:{revenue:0,commission:0,sent_orders:0,earned:0,paid:0,balance:0}});
const numberFormat=new Intl.NumberFormat('en-US');
export const mm=(n:number)=>numberFormat.format(n).replace(/[0-9]/g,d=>'၀၁၂၃၄၅၆၇၈၉'[Number(d)]);
export const money=(n:number)=>`${mm(n)} ကျပ်`;
const months=['ဇန်နဝါရီ','ဖေဖော်ဝါရီ','မတ်','ဧပြီ','မေ','ဇွန်','ဇူလိုင်','ဩဂုတ်','စက်တင်ဘာ','အောက်တိုဘာ','နိုဝင်ဘာ','ဒီဇင်ဘာ'];
export const day=(s:string)=>{const d=new Date(new Date(s).getTime()+390*60000);return `${mm(d.getUTCDate())} ${months[d.getUTCMonth()]}`};
export const fullDate=()=>`${day(new Date().toISOString())}၊ ${mm(new Date().getFullYear())}`;
export const statusText={pending:'မပို့ရသေး',sent:'ပို့ပြီး',cancelled:'ပယ်ဖျက်ပြီး'};
export const ago=(days:number)=>new Date(Date.now()-days*86400000).toISOString();
export function balances(data:Data,id:number){const u=data.users.find(u=>u.id===id);return{earned:u?.earned||0,paid:u?.paid||0,balance:u?.balance||0};}
export const API=import.meta.env?.VITE_API_URL||'/api';
