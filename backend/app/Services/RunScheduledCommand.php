<?php
namespace App\Services;
use Illuminate\Support\Facades\{Artisan,File};
class RunScheduledCommand {
 public function __construct(public readonly string $command,public readonly array $arguments,public readonly string $logFile){}
 public function __invoke():void {
  $path=storage_path('logs/'.$this->logFile);
  File::append($path,'['.now()->toIso8601String().'] START '.$this->command."\n",true);
  try{
   // Artisan::call executes in this PHP process; no shell/proc_open is needed.
   $code=Artisan::call($this->command,$this->arguments);
   File::append($path,Artisan::output().'['.now()->toIso8601String().'] EXIT '.$code.' '.$this->command."\n",true);
   if($code!==0)throw new \RuntimeException('Scheduled task failed: '.$this->command);
  }catch(\Throwable $error){
   // Do not put exception messages, endpoints or credentials in the task log.
   File::append($path,'['.now()->toIso8601String().'] ERROR '.get_class($error).' '.$this->command."\n",true);
   throw $error;
  }
 }
}
