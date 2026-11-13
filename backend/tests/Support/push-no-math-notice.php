<?php
namespace Minishlink\WebPush {
 // Exercise the optional-extension notice even when the test PHP has GMP enabled.
 function extension_loaded(string $extension):bool{return in_array($extension,['gmp','bcmath'],true)?false:\extension_loaded($extension);}
}
namespace {
 require __DIR__.'/../../vendor/autoload.php';$app=require __DIR__.'/../../bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 try{
  if(config('database.connections.'.config('database.default').'.database')!=='aeae_testing')throw new \RuntimeException('Probe requires aeae_testing.');
  $keys=\Minishlink\WebPush\VAPID::createVapidKeys();config(['webpush.public_key'=>$keys['publicKey'],'webpush.private_key'=>$keys['privateKey'],'webpush.subject'=>'https://example.com']);
  $device=new \App\Models\WebPushSubscription(['endpoint'=>'https://fcm.googleapis.com/fcm/send/mock-only','public_key'=>$keys['publicKey'],'auth_token'=>rtrim(strtr(base64_encode(random_bytes(16)),'+/','-_'),'=')]);
  $handler=new \GuzzleHttp\Handler\MockHandler([new \GuzzleHttp\Psr7\Response(201)]);
  $delivery=new \App\Services\PushDelivery(new \GuzzleHttp\Client(['handler'=>\GuzzleHttp\HandlerStack::create($handler)]));
  $result=$delivery->send($device,['id'=>1,'recipient_id'=>1,'title'=>'Test','body'=>'Test']);if(!$result['success'])throw new \RuntimeException('Mock transport failed.');
  echo "PASS: optional GMP/BCMath notice did not abort encrypted push; controlled HTTP transport only.\n";
 }catch(\Throwable $error){fwrite(STDERR,'Probe failed: '.get_class($error)."\n");exit(1);}
}
