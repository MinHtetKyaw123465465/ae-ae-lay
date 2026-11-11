<?php
namespace Database\Seeders;
use App\Models\User;
use Illuminate\Database\Seeder;
class DatabaseSeeder extends Seeder {
 public function run():void {
  if(!app()->environment(['local','testing']))throw new \RuntimeException('Use app:create-owner in production.');
  // Leave existing owner credentials and all existing business records untouched.
  if(User::where('role','owner')->exists())return;
  if(User::where('phone','09900000001')->exists())throw new \RuntimeException('The owner phone is already in use. Use app:create-owner with another phone.');
  $owner=new User(['name'=>'အဲအဲလေး','phone'=>'09900000001','password'=>'LocalFixture2026!']);
  $owner->forceFill(['role'=>'owner','status'=>'approved'])->save();
 }
}
