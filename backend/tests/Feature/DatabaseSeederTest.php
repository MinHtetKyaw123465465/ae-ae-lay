<?php
namespace Tests\Feature;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
class DatabaseSeederTest extends TestCase {
 use RefreshDatabase;
 public function test_seeding_only_creates_the_owner_and_is_repeatable():void {
  $this->seed(DatabaseSeeder::class);$this->seed(DatabaseSeeder::class);
  $this->assertDatabaseCount('users',1);$this->assertDatabaseHas('users',['phone'=>'09900000001','role'=>'owner','status'=>'approved']);
  foreach(['products','orders','payouts','shop_notifications','agent_changes','web_push_subscriptions'] as $table)$this->assertDatabaseCount($table,0);
 }
 public function test_seeding_preserves_existing_owner_credentials():void {
  $owner=new User(['name'=>'ပိုင်ရှင်','phone'=>'09987654321','password'=>'PrivatePassword2026!']);$owner->forceFill(['role'=>'owner','status'=>'approved'])->save();
  $this->seed(DatabaseSeeder::class);$this->assertDatabaseCount('users',1);$this->assertSame('09987654321',$owner->refresh()->phone);$this->assertTrue(Hash::check('PrivatePassword2026!',$owner->password));
 }
}
