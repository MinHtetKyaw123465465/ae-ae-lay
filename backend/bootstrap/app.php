<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
return Application::configure(basePath:dirname(__DIR__))
 ->withRouting(web:__DIR__.'/../routes/web.php',commands:__DIR__.'/../routes/console.php',health:'/up')
 ->withMiddleware(function(Middleware $middleware):void {$middleware->redirectGuestsTo('/');$middleware->appendToGroup('web', \App\Http\Middleware\NoStore::class);})
 ->withExceptions(function(Exceptions $exceptions):void {
  $exceptions->respond(fn(\Symfony\Component\HttpFoundation\Response $response)=>\App\Http\Middleware\NoStore::secure($response));
  $exceptions->render(function(\Illuminate\Database\UniqueConstraintViolationException $e,Request $r){if($r->is('api/*'))return response()->json(['message'=>'အချက်အလက် ထပ်နေပါသည်။ စာရင်းကို ပြန်စစ်ပါ။'],409);});
  $exceptions->shouldRenderJsonWhen(fn(Request $r,\Throwable $e)=>$r->is('api/*')||$r->expectsJson());
  $exceptions->render(function(ValidationException $e,Request $r){if($r->is('api/*'))return response()->json(['message'=>collect($e->errors())->flatten()->first(),'errors'=>$e->errors()],422);});
  $exceptions->render(function(AuthenticationException $e,Request $r){if($r->is('api/*'))return response()->json(['message'=>'အကောင့်ဝင်ရန် လိုအပ်ပါသည်။'],401);});
  $exceptions->render(function(\Symfony\Component\HttpKernel\Exception\HttpException $e,Request $r){if($r->is('api/*')){ $code=$e->getStatusCode();$message=match($code){419=>'အချိန်ကျော်လွန်သွားပါပြီ။ ပြန်လည်ကြိုးစားပါ။',429=>'ခဏစောင့်ပြီး ပြန်လည်ကြိုးစားပါ။',404=>'အချက်အလက်ကို မတွေ့ပါ။',default=>$e->getMessage()};return response()->json(['message'=>$message],$code);}});
  $exceptions->render(function(\Throwable $e,Request $r){if($r->is('api/*')&&!($e instanceof ValidationException)&&!($e instanceof AuthenticationException)&&(!($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface)||$e->getStatusCode()>=500))return response()->json(['message'=>'ဆာဗာတွင် အမှားဖြစ်နေပါသည်။ ပြန်ကြိုးစားပါ။'],500);});
 })->create();
