<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
class WorkNotifications extends Command {
 protected $signature='app:work-notifications {--max-time=50 : Worker lifetime in seconds (1-50)}';
 protected $description='Drain pending notifications as a bounded Laravel scheduled task';
 public function handle():int {
  $seconds=filter_var($this->option('max-time'),FILTER_VALIDATE_INT);
  if($seconds===false||$seconds<1||$seconds>50){$this->error('အချိန်ကို ၁ မှ ၅၀ စက္ကန့်အတွင်း သတ်မှတ်ပါ။');return self::FAILURE;}
  $lock=@fopen(storage_path('framework/notification-worker.lock'),'c');if(!$lock){$this->error('Worker ဖိုင် သိမ်းဆည်းခွင့်ကို စစ်ပါ။');return self::FAILURE;}
  try{
   if(!flock($lock,LOCK_EX|LOCK_NB)){$this->info('Worker လည်ပတ်နေပြီးပါပြီ။');return self::SUCCESS;}
   // Surface queue connectivity/schema errors instead of an apparently clean exit.
   $this->laravel['queue']->connection()->size();
   return $this->call('queue:work',['--stop-when-empty'=>true,'--sleep'=>1,'--tries'=>3,'--timeout'=>60,'--max-time'=>$seconds]);
  }finally{flock($lock,LOCK_UN);fclose($lock);}
 }
}
