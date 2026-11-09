<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
class User extends Authenticatable {
 use HasFactory,Notifiable;
 protected $fillable=['name','phone','password'];
 protected $hidden=['password','remember_token','email','email_verified_at'];
 protected function casts():array{return ['password'=>'hashed'];}
}
