<?php
namespace App\Rules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
class SafePassword implements ValidationRule {
 public function validate(string $attribute,mixed $value,Closure $fail):void {
  if(!is_string($value))return;
  if(str_contains($value,"\0")){$fail('စကားဝှက်တွင် မမှန်သော စာလုံး ပါဝင်နေသည်။');return;}
  // Bcrypt truncates after 72 bytes, including multi-byte Burmese text.
  if(config('hashing.driver','bcrypt')==='bcrypt'&&strlen($value)>72)$fail('စကားဝှက် ရှည်လွန်းပါသည်။ ပိုတိုသော စကားဝှက်ကို အသုံးပြုပါ။');
 }
}
