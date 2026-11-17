<?php
namespace App\Console\Commands;
use App\Services\TelegramNotifications;
use Illuminate\Console\Command;
class TestTelegram extends Command {
 protected $signature='app:telegram-test';
 protected $description='Send a Telegram test message to the configured admin chat';
 public function handle(TelegramNotifications $telegram):int {
  if($telegram->sendTest()){$this->info('Telegram စမ်းသပ်စာ ပို့ပြီးပါပြီ။ Admin Telegram ကို စစ်ပေးပါ။');return self::SUCCESS;}
  $this->error(match($telegram->failureReason()){
   'not_configured'=>'TELEGRAM_BOT_TOKEN နှင့် TELEGRAM_ADMIN_CHAT_ID ကို .env ထဲ မှန်ကန်စွာ ဖြည့်ပြီး config:cache ပြန်လုပ်ပါ။',
   'invalid_token'=>'Bot Token မမှန်ပါ။ BotFather မှ ရရှိထားသော Token ကို စစ်ပါ။',
   'chat_forbidden'=>'Admin က Bot ကို Start နှိပ်ထားရမည်။ Bot ကို block လုပ်မထားကြောင်း စစ်ပါ။',
   'invalid_chat'=>'Admin Chat ID နှင့် Bot ကို Start နှိပ်ထားမှုကို စစ်ပါ။',
   'rate_limited'=>'Telegram ပို့ဆောင်မှု အရေအတွက်ကန့်သတ်ချက် ပြည့်နေပါသည်။ ခဏစောင့်ပြီး စမ်းပါ။',
   default=>'Telegram ပို့၍ မရပါ။ ဆာဗာအင်တာနက်၊ SSL နှင့် Laravel log ကို စစ်ပါ။',
  });return self::FAILURE;
 }
}
