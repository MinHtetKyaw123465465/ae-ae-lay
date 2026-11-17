<?php
namespace App\Console\Commands;
use App\Services\TelegramNotifications;
use Illuminate\Console\Command;
class TestTelegram extends Command {
 protected $signature='app:telegram-test {--ipv4 : Try IPv4 for this test only}';
 protected $description='Send a Telegram test message to the configured admin chat';
 public function handle(TelegramNotifications $telegram):int {
  $previous=config('services.telegram.force_ipv4',false);
  try{
  if($this->option('ipv4'))config(['services.telegram.force_ipv4'=>true]);
  if($telegram->sendTest()){$this->info('Telegram စမ်းသပ်စာ ပို့ပြီးပါပြီ။ Admin Telegram ကို စစ်ပေးပါ။');return self::SUCCESS;}
  if($telegram->curlError()!==null)$this->line('cURL error: '.$telegram->curlError());
  $this->error(match($telegram->failureReason()){
   'dns_error'=>'PHP က api.telegram.org လိပ်စာကို ရှာမတွေ့ပါ။ ဆာဗာ DNS ကို စစ်ပါ။',
   'proxy_dns_error'=>'PHP ၏ proxy လိပ်စာကို ရှာမတွေ့ပါ။ ဆာဗာ proxy ဆက်တင်ကို စစ်ပါ။',
   'connect_error'=>'PHP မှ Telegram ဆာဗာကို ချိတ်ဆက်၍ မရပါ။ Outbound HTTPS ကို စစ်ပါ။',
   'timeout'=>'PHP ချိတ်ဆက်ချိန် ကုန်သွားပါသည်။ app:telegram-test --ipv4 ဖြင့် ထပ်စမ်းနိုင်သည်။',
   'tls_error'=>'PHP ၏ SSL/CA certificate စစ်ဆေးမှု မအောင်မြင်ပါ။ PHP certificate ဆက်တင်ကို စစ်ပါ။',
   'not_configured'=>'TELEGRAM_BOT_TOKEN နှင့် TELEGRAM_ADMIN_CHAT_ID ကို .env ထဲ မှန်ကန်စွာ ဖြည့်ပြီး config:cache ပြန်လုပ်ပါ။',
   'invalid_token'=>'Bot Token မမှန်ပါ။ BotFather မှ ရရှိထားသော Token ကို စစ်ပါ။',
   'chat_forbidden'=>'Admin က Bot ကို Start နှိပ်ထားရမည်။ Bot ကို block လုပ်မထားကြောင်း စစ်ပါ။',
   'invalid_chat'=>'Admin Chat ID နှင့် Bot ကို Start နှိပ်ထားမှုကို စစ်ပါ။',
   'rate_limited'=>'Telegram ပို့ဆောင်မှု အရေအတွက်ကန့်သတ်ချက် ပြည့်နေပါသည်။ ခဏစောင့်ပြီး စမ်းပါ။',
   default=>'Telegram ပို့၍ မရပါ။ ဆာဗာအင်တာနက်၊ SSL နှင့် Laravel log ကို စစ်ပါ။',
  });return self::FAILURE;
  }finally{config(['services.telegram.force_ipv4'=>$previous]);}
 }
}
