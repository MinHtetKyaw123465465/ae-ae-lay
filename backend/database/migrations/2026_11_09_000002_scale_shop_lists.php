<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up():void {
  Schema::table('products',fn(Blueprint $t)=>$t->unsignedInteger('lock_version')->default(0));
  Schema::table('orders',function(Blueprint $t){$t->uuid('request_key')->nullable()->unique();$t->index(['status','sent_at','id'],'orders_status_sent');$t->index(['agent_id','status','sent_at'],'orders_agent_sent');$t->index(['agent_id','created_at','id'],'orders_agent_created');$t->index(['status','created_at','id'],'orders_status_created');});
  Schema::table('payouts',function(Blueprint $t){$t->uuid('request_key')->nullable()->unique();$t->index(['agent_id','created_at','id'],'payouts_agent_created');});
  Schema::table('users',fn(Blueprint $t)=>$t->index(['role','status','id'],'users_role_status'));
  DB::table('orders')->where('status','sent')->whereNull('sent_at')->update(['sent_at'=>DB::raw('created_at')]);
 }
 public function down():void {
  Schema::table('users',fn(Blueprint $t)=>$t->dropIndex('users_role_status'));
  Schema::table('payouts',function(Blueprint $t){$t->dropIndex('payouts_agent_created');$t->dropUnique(['request_key']);$t->dropColumn('request_key');});
  Schema::table('orders',function(Blueprint $t){foreach(['orders_status_sent','orders_agent_sent','orders_agent_created','orders_status_created'] as $i)$t->dropIndex($i);$t->dropUnique(['request_key']);$t->dropColumn('request_key');});
  Schema::table('products',fn(Blueprint $t)=>$t->dropColumn('lock_version'));
 }
};
