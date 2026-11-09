<?php
namespace App\Services;
use App\Models\WebPushSubscription;
use GuzzleHttp\Client;
use Psr\Http\Client\ClientInterface;
use Minishlink\WebPush\{WebPush,Subscription};
class PushDelivery {
 private ClientInterface $client;
 public function __construct(?ClientInterface $client=null){
  $options=['timeout'=>5,'connect_timeout'=>3,'allow_redirects'=>false];
  if($proxy=getenv('HTTPS_PROXY')?:getenv('https_proxy'))$options['proxy']=$proxy;
  $ca=getenv('SSL_CERT_FILE')?:getenv('CURL_CA_BUNDLE');if($ca&&is_file($ca))$options['verify']=$ca;
  $this->client=$client??new Client($options);
 }
 public function send(WebPushSubscription $device,array $payload):array {
  $push=new WebPush(['VAPID'=>['subject'=>config('webpush.subject'),'publicKey'=>config('webpush.public_key'),'privateKey'=>config('webpush.private_key')]],['TTL'=>86400,'urgency'=>'normal'],$this->client);
  $subscription=Subscription::create(['endpoint'=>$device->endpoint,'publicKey'=>$device->public_key,'authToken'=>$device->auth_token,'contentEncoding'=>'aes128gcm']);
  $result=$push->sendOneNotification($subscription,json_encode($payload,JSON_UNESCAPED_UNICODE),['topic'=>substr(hash('sha256',(string)$payload['id']),0,32)]);
  return ['success'=>$result->isSuccess(),'expired'=>$result->isSubscriptionExpired(),'status'=>$result->getResponse()?->getStatusCode()];
 }
}
