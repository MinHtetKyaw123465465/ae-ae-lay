<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\NotificationController;
Route::prefix('api')->group(function(){
 // The private browser push endpoint is a capability; no login cookie is needed.
 Route::post('/push/verify',[NotificationController::class,'verify'])->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,\Illuminate\Session\Middleware\StartSession::class,\Illuminate\View\Middleware\ShareErrorsFromSession::class])->middleware('throttle:600,1,aeae-push-verify:');
 Route::get('/csrf',fn()=>response()->json(['token'=>csrf_token()]));
 Route::post('/register',[ShopController::class,'register'])->middleware('throttle:5,1,aeae-register:');
 Route::post('/login',[ShopController::class,'login'])->middleware('throttle:shop-login');
 Route::middleware(['auth','auth.session'])->group(function(){
  Route::get('/notifications',[NotificationController::class,'index']);
  Route::patch('/notifications/read-all',[NotificationController::class,'readAll']);
  Route::patch('/notifications/{notification}/read',[NotificationController::class,'read'])->whereNumber('notification');
  Route::post('/push/setup',[NotificationController::class,'setup'])->middleware('throttle:5,1,aeae-push-setup:');
  Route::get('/push/settings',[NotificationController::class,'settings']);
  Route::post('/push/test',[NotificationController::class,'test'])->middleware('throttle:3,1,aeae-push-test:');
  Route::post('/push/status',[NotificationController::class,'status']);
  Route::post('/push/subscribe',[NotificationController::class,'subscribe'])->middleware('throttle:20,1,aeae-push:');
  Route::delete('/push/subscribe',[NotificationController::class,'unsubscribe']);
  Route::get('/me',function(Request $r){abort_unless($r->user()->status==='approved',403,'ပိုင်ရှင်၏ အတည်ပြုချက်ကို စောင့်ဆိုင်းပေးပါ။');return response()->json($r->user());});
  Route::post('/logout',[ShopController::class,'logout']);
  Route::put('/account',[\App\Http\Controllers\AccountController::class,'update'])->middleware('throttle:10,1,aeae-account:');
  Route::put('/agents/{agent}/password',[\App\Http\Controllers\AccountController::class,'resetAgentPassword'])->whereNumber('agent')->middleware('throttle:5,1,aeae-agent-password:');
  Route::put('/password',[ShopController::class,'password'])->middleware('throttle:5,1,aeae-password:');
  Route::get('/options/{type}',[ShopController::class,'options']);
  Route::get('/orders/export',[ShopController::class,'export'])->middleware('throttle:5,1,aeae-export:');
  Route::get('/orders/{order}',[ShopController::class,'showOrder'])->whereNumber('order');
  Route::get('/products/{product}/image',[ShopController::class,'productImage'])->whereNumber('product');
  Route::get('/state',[ShopController::class,'state']);
  Route::get('/rankings',[ShopController::class,'rankings']);
  Route::post('/orders',[ShopController::class,'storeOrder']);
  Route::patch('/orders/{order}/status',[ShopController::class,'status'])->whereNumber('order');
  Route::post('/products',[ShopController::class,'product']);
  Route::put('/products/{product}',[ShopController::class,'product'])->whereNumber('product');
  Route::get('/agents/{agent}',[\App\Http\Controllers\AgentController::class,'show'])->whereNumber('agent');
  Route::get('/agents/{agent}/history',[\App\Http\Controllers\AgentController::class,'history'])->whereNumber('agent');
  Route::put('/agents/{agent}',[\App\Http\Controllers\AgentController::class,'update'])->whereNumber('agent');
  Route::patch('/agents/{agent}',[\App\Http\Controllers\AgentController::class,'status'])->whereNumber('agent');
  Route::post('/payouts',[ShopController::class,'payout']);
 });
});

Route::get('/{path?}',function(){abort_unless(file_exists(public_path('index.html')),404);return response()->file(public_path('index.html'));})->where('path','(?!api(?:/|$)).*');
