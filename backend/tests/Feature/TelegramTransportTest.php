<?php
namespace Tests\Feature;
use App\Services\TelegramNotifications;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\{Http,Log};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
class TelegramTransportTest extends TestCase {
 private const TOKEN='123456789:TEST_ONLY_TELEGRAM_TOKEN';
 protected function setUp():void{parent::setUp();Http::preventStrayRequests();config(['services.telegram.bot_token'=>self::TOKEN,'services.telegram.admin_chat_id'=>'123456789','services.telegram.force_ipv4'=>false]);}
 public static function failures():array{return [[60,'tls_error'],[28,'timeout'],[6,'dns_error'],[7,'connect_error'],[5,'proxy_dns_error']];}
 private function failure(int $code):ConnectionException{$cause=new ConnectException('URL includes '.self::TOKEN,new Request('POST','https://api.telegram.org/bot'.self::TOKEN.'/sendMessage'),null,['errno'=>$code,'error'=>'SECRET '.self::TOKEN,'url'=>'SECRET '.self::TOKEN]);return new ConnectionException('SECRET '.self::TOKEN,0,$cause);}
 #[DataProvider('failures')]
 public function test_transport_error_codes_are_extracted_without_logging_secret_urls(int $code,string $reason):void{
  Log::spy();Http::fake(fn()=>throw $this->failure($code));$telegram=app(TelegramNotifications::class);$this->assertFalse($telegram->sendTest());$this->assertSame($code,$telegram->curlError());$this->assertSame($reason,$telegram->failureReason());
  Log::shouldHaveReceived('warning')->withArgs(fn($message,$context)=>$message==='Telegram transport unavailable'&&$context['curl_errno']===$code&&!str_contains(json_encode($context),self::TOKEN)&&!isset($context['url'])&&!isset($context['error']));
 }
 public function test_numeric_code_can_be_read_when_the_exception_has_no_handler_context():void{Http::fake(fn()=>throw new ConnectionException('cURL error 60: SECRET '.self::TOKEN));$telegram=app(TelegramNotifications::class);$this->assertFalse($telegram->sendTest());$this->assertSame(60,$telegram->curlError());}
 public function test_cli_reports_safe_certificate_error_instead_of_generic_failure():void{Http::fake(fn()=>throw $this->failure(60));$this->artisan('app:telegram-test')->expectsOutput('cURL error: 60')->expectsOutput('PHP ၏ SSL/CA certificate စစ်ဆေးမှု မအောင်မြင်ပါ။ PHP certificate ဆက်တင်ကို စစ်ပါ။')->assertFailed();}
 public function test_ipv4_diagnostic_is_temporary_and_keeps_certificate_verification_enabled():void{
  $options=[];Http::fake(function($request,$settings)use(&$options){$options[]=$settings;return Http::response(['ok'=>true],200);});
  $this->artisan('app:telegram-test',['--ipv4'=>true])->assertSuccessful();$this->assertSame('v4',$options[0]['force_ip_resolve']);$this->assertNotSame(false,$options[0]['verify']??true);$this->assertFalse(config('services.telegram.force_ipv4'));
  $this->assertTrue(app(TelegramNotifications::class)->sendTest());$this->assertArrayNotHasKey('force_ip_resolve',$options[1]);
 }
 public function test_confirmed_ipv4_configuration_applies_to_normal_sends():void{config(['services.telegram.force_ipv4'=>true]);Http::fake(function($r,$options){$this->assertSame('v4',$options['force_ip_resolve']);return Http::response(['ok'=>true],200);});$this->assertTrue(app(TelegramNotifications::class)->sendTest());}
}
