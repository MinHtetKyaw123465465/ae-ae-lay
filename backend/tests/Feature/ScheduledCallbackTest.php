<?php
namespace Tests\Feature;
use App\Services\RunScheduledCommand;
use Illuminate\Console\Scheduling\{Schedule,CallbackEvent};
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
class ScheduledCallbackTest extends TestCase {
 public function test_all_scheduled_tasks_are_named_in_process_callbacks():void{
  $events=app(Schedule::class)->events();$this->assertCount(2,$events);
  foreach($events as $event){$this->assertInstanceOf(CallbackEvent::class,$event);$this->assertNull($event->command);$this->assertTrue($event->withoutOverlapping);$this->assertFalse($event->runInBackground);}
 }
 public function test_callback_calls_artisan_in_process_and_creates_a_log_even_when_queue_is_empty():void{
  $name='callback-test-'.bin2hex(random_bytes(6)).'.log';$path=storage_path('logs/'.$name);
  Artisan::shouldReceive('call')->once()->with('app:work-notifications',['--max-time'=>45])->andReturn(0);Artisan::shouldReceive('output')->once()->andReturn('');
  try{(new RunScheduledCommand('app:work-notifications',['--max-time'=>45],$name))();$text=file_get_contents($path);$this->assertStringContainsString('START app:work-notifications',$text);$this->assertStringContainsString('EXIT 0 app:work-notifications',$text);}finally{if(is_file($path))unlink($path);}
 }
 public function test_nonzero_command_exit_is_not_reported_as_success():void{
  $name='callback-failed-'.bin2hex(random_bytes(6)).'.log';$path=storage_path('logs/'.$name);
  Artisan::shouldReceive('call')->once()->with('app:work-notifications',[])->andReturn(1);Artisan::shouldReceive('output')->once()->andReturn('');
  try{try{(new RunScheduledCommand('app:work-notifications',[],$name))();$this->fail('Expected scheduled task failure.');}catch(\RuntimeException){$text=file_get_contents($path);$this->assertStringContainsString('EXIT 1',$text);$this->assertStringContainsString('ERROR RuntimeException',$text);}}finally{if(is_file($path))unlink($path);}
 }
}
