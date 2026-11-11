<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::table('users',function(Blueprint $t){
   $t->unsignedBigInteger('opening_quantity')->default(0);
   $t->unsignedBigInteger('opening_balance')->default(0);
   $t->date('opening_date')->nullable();
   $t->unsignedInteger('agent_version')->default(0);
  });
  Schema::create('agent_changes',function(Blueprint $t){
   $t->id();$t->foreignId('agent_id')->constrained('users');$t->foreignId('actor_id')->constrained('users');
   $t->json('before');$t->json('after');$t->string('note',250)->nullable();$t->timestamp('created_at');
   $t->index(['agent_id','id']);
  });
 }
 public function down():void {
  Schema::dropIfExists('agent_changes');
  Schema::table('users',fn(Blueprint $t)=>$t->dropColumn(['opening_quantity','opening_balance','opening_date','agent_version']));
 }
};
