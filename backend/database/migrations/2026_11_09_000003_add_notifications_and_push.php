<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('shop_notifications',function(Blueprint $t){$t->id();$t->foreignId('recipient_id')->constrained('users');$t->string('type',40);$t->string('title',150);$t->string('body',250);$t->string('destination',30);$t->unsignedBigInteger('resource_id')->nullable();$t->timestamp('read_at')->nullable();$t->timestamps();$t->index(['recipient_id','read_at','id'],'notifications_recipient_unread');$t->index(['recipient_id','id'],'notifications_recipient_latest');});
  Schema::create('web_push_subscriptions',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained('users');$t->char('endpoint_hash',64)->unique();$t->text('endpoint');$t->text('public_key');$t->text('auth_token');$t->timestamps();$t->index('user_id');});
 }
 public function down():void{Schema::dropIfExists('web_push_subscriptions');Schema::dropIfExists('shop_notifications');}
};
