<?php
namespace App\Services;
use Illuminate\Support\Facades\{Crypt,Storage};
use Minishlink\WebPush\VAPID;
class PushKeys {
 public const FILE='push/vapid.enc';
 private function valid(array $keys):bool {
  try{VAPID::validate(['subject'=>config('webpush.subject'),'publicKey'=>($keys['public_key']??null),'privateKey'=>($keys['private_key']??null)]);return true;}catch(\Throwable){return false;}
 }
 public function configured():bool {
  $keys=['public_key'=>config('webpush.public_key'),'private_key'=>config('webpush.private_key')];
  // Explicit environment settings always take precedence. Never replace partial keys.
  if($keys['public_key']||$keys['private_key'])return $this->valid($keys);
  try{
   $disk=Storage::disk('local');if(!$disk->exists(self::FILE))return false;
   $keys=json_decode(Crypt::decryptString($disk->get(self::FILE)),true,512,JSON_THROW_ON_ERROR);
   if(!$this->valid($keys))return false;
   config(['webpush.public_key'=>$keys['public_key'],'webpush.private_key'=>$keys['private_key']]);return true;
  }catch(\Throwable){return false;}
 }
 public function initialize():void {
  if($this->configured())return;
  $disk=Storage::disk('local');$disk->makeDirectory('push');$directory=$disk->path('push');@chmod($directory,0700);
  $lock=@fopen($directory.'/.setup.lock','c');if(!$lock)throw new \RuntimeException('Push storage is not writable.');
  try{
   if(!flock($lock,LOCK_EX))throw new \RuntimeException('Could not lock push settings.');
   if($this->configured())return;
   if(config('webpush.public_key')||config('webpush.private_key')||$disk->exists(self::FILE))throw new \RuntimeException('Existing push keys require server repair; refusing rotation.');
   $new=VAPID::createVapidKeys();$keys=['public_key'=>$new['publicKey'],'private_key'=>$new['privateKey']];
   if(!$this->valid($keys))throw new \RuntimeException('Push settings are invalid.');
   // Atomic publication; a running worker can load these keys on its next job.
   $path=$disk->path(self::FILE);$temporary=$path.'.'.bin2hex(random_bytes(8));
   try{if(file_put_contents($temporary,Crypt::encryptString(json_encode($keys,JSON_THROW_ON_ERROR)),LOCK_EX)===false)throw new \RuntimeException('Could not save push settings.');chmod($temporary,0600);if(!rename($temporary,$path))throw new \RuntimeException('Could not publish push settings.');}finally{if(is_file($temporary))unlink($temporary);}
   config(['webpush.public_key'=>$keys['public_key'],'webpush.private_key'=>$keys['private_key']]);
  }finally{flock($lock,LOCK_UN);fclose($lock);}
 }
}
