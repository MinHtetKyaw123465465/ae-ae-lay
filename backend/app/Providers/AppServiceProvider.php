<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
class AppServiceProvider extends ServiceProvider {
 public function register():void {}
 public function boot():void {
  RateLimiter::for('shop-login',function(Request $r){$phone=$r->input('phone');return [Limit::perMinute(10)->by('account:'.hash('sha256',(is_string($phone)?$phone:'').'|'.$r->ip())),Limit::perMinute(60)->by('network:'.$r->ip())];});
 }
}
