<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::table('users',function(Blueprint $t){$t->string('email')->nullable()->change();$t->string('phone',20)->unique();$t->enum('role',['owner','agent'])->default('agent');$t->enum('status',['pending','approved','rejected'])->default('pending');});
  Schema::create('products',function(Blueprint $t){$t->id();$t->string('name',150);$t->text('description');$t->unsignedBigInteger('price');$t->unsignedBigInteger('commission');$t->unsignedInteger('stock');$t->longText('image');$t->boolean('active')->default(true);$t->timestamps();});
  Schema::create('orders',function(Blueprint $t){$t->id();$t->foreignId('agent_id')->constrained('users');$t->foreignId('product_id')->constrained();$t->string('customer',100);$t->string('phone',20);$t->text('address');$t->unsignedInteger('quantity');$t->unsignedBigInteger('unit_price');$t->unsignedBigInteger('unit_commission');$t->enum('status',['pending','sent','cancelled'])->default('pending');$t->timestamp('sent_at')->nullable();$t->timestamps();$t->index(['agent_id','status']);$t->index('created_at');});
  Schema::create('payouts',function(Blueprint $t){$t->id();$t->foreignId('agent_id')->constrained('users');$t->unsignedBigInteger('amount');$t->string('reference',250);$t->timestamps();});
 }
 public function down():void {Schema::dropIfExists('payouts');Schema::dropIfExists('orders');Schema::dropIfExists('products');Schema::table('users',function(Blueprint $t){$t->dropColumn(['phone','role','status']);});}
};
