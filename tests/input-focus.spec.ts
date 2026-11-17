import {test,expect} from '@playwright/test';
test.use({serviceWorkers:'block',viewport:{width:390,height:844},isMobile:true,hasTouch:true});
for(const phone of ['09900000001','09900000002'])test(`phone order fields retain their DOM, draft and caret when refocused (${phone})`,async({page})=>{
 await page.goto('/');await page.getByLabel('ဖုန်းနံပါတ်').fill(phone);await page.getByLabel('စကားဝှက်',{exact:true}).fill('LocalFixture2026!');await page.getByRole('button',{name:'အကောင့်ဝင်မည်',exact:true}).click();await page.getByRole('button',{name:'အော်ဒါအသစ်တင်မည်',exact:true}).first().click();
 const dialog=page.getByRole('dialog'),address=dialog.locator('textarea[name=address]');
 await dialog.getByLabel('ဝယ်သူအမည်').fill('မသီတာ');await dialog.getByLabel('ဖုန်းနံပါတ်').fill('09123456789');await address.fill('အမှတ် ၁၂၊ ရန်ကုန်မြို့');
 const handle=await address.elementHandle();let wakes=0;page.on('request',r=>{if(new URL(r.url()).pathname==='/api/me')wakes++});
 for(const name of ['customer','phone','address']){
  const field=dialog.locator(`[name=${name}]`);await field.blur();await field.tap();await expect(field).toBeFocused();
  await field.evaluate((el:HTMLInputElement|HTMLTextAreaElement)=>el.setSelectionRange(2,2));
  await page.evaluate(()=>{window.dispatchEvent(new Event('focus'));window.dispatchEvent(new Event('pageshow'))});
  await page.waitForTimeout(100);await expect(field).toBeFocused();expect(await field.evaluate((el:HTMLInputElement|HTMLTextAreaElement)=>[el.selectionStart,el.selectionEnd])).toEqual([2,2]);expect(await field.evaluate(el=>parseFloat(getComputedStyle(el).fontSize))).toBeGreaterThanOrEqual(16);
 }
 await dialog.locator('[name=quantity]').fill('');await expect(dialog.locator('[name=quantity]')).toHaveValue('');await dialog.locator('[name=quantity]').fill('2');
 expect(await address.evaluate((el,original)=>el===original,handle)).toBe(true);await expect(address).toHaveValue('အမှတ် ၁၂၊ ရန်ကုန်မြို့');expect(wakes).toBe(0);
 // Keyboard viewport resize must not remove the focused editor or the form.
 await address.tap();await page.setViewportSize({width:390,height:480});await expect(address).toBeFocused();await expect(dialog).toBeVisible();await page.keyboard.insertText(' စမ်းသပ်');expect(await address.inputValue()).toContain('စမ်းသပ်');expect(await page.evaluate(()=>document.body.scrollWidth)).toBeLessThanOrEqual(390);
});

test('picker page jumps never nest or submit the order form and retain customer details',async({page})=>{
 await page.goto('/');await page.getByLabel('ဖုန်းနံပါတ်').fill('09900000001');await page.getByLabel('စကားဝှက်',{exact:true}).fill('LocalFixture2026!');await page.getByRole('button',{name:'အကောင့်ဝင်မည်',exact:true}).click();await page.getByRole('button',{name:'အော်ဒါအသစ်တင်မည်',exact:true}).first().click();
 const dialog=page.getByRole('dialog');await expect(dialog.locator('.option-select').first().getByLabel('စာမျက်နှာအမှတ်')).toBeVisible();await expect(dialog.locator('form form')).toHaveCount(0);
 await dialog.getByLabel('ဝယ်သူအမည်').fill('မသီတာ');await dialog.getByLabel('ဖုန်းနံပါတ်').fill('09123456789');await dialog.locator('[name=address]').fill('ရန်ကုန်');
 let submissions=0;await dialog.locator('form').evaluate(el=>{el.addEventListener('submit',()=>el.setAttribute('data-submitted','yes'))});page.on('request',r=>{if(r.method()==='POST'&&new URL(r.url()).pathname==='/api/orders')submissions++});
 const picker=dialog.locator('.option-select').first(),jump=picker.getByLabel('စာမျက်နှာအမှတ်');await jump.fill('2');await jump.press('Enter');await expect(picker.getByRole('combobox')).toBeEnabled();await expect(jump).toHaveValue('2');await expect(dialog.getByLabel('ဝယ်သူအမည်')).toHaveValue('မသီတာ');await expect(dialog.locator('[name=address]')).toHaveValue('ရန်ကုန်');expect(submissions).toBe(0);await expect(dialog.locator('form')).not.toHaveAttribute('data-submitted','yes');
 await jump.fill('bad');await jump.press('Enter');await expect(jump).toHaveValue('2');expect(await dialog.locator('form').evaluate((el:HTMLFormElement)=>el.checkValidity())).toBe(true);
});
