const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const path=require('node:path');
const fs=require('node:fs');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const html=execFileSync('php',['-n',path.join(__dirname,'job_search_dialog_fixture.php')],{encoding:'utf8'});
(async()=>{
 const browser=await chromium.launch({headless:true});
 try {
  for(const scenario of ['success','limited','cancel','failure']) {
   const page=await browser.newPage({viewport:{width:390,height:844}});
   let steps=0,release;
   const errors=[];page.on('pageerror',e=>errors.push(e.message));
   await page.route('http://jema.test/**',async route=>{
    if(route.request().method()==='GET')return route.fulfill({contentType:'text/html',body:html});
    const body=route.request().postData()||'';
    if(body.includes('advance_verified_job_search')) {
     steps++;
     if(steps===1)await new Promise(resolve=>release=resolve);
     try {await route.fulfill({status:scenario==='failure'?422:200,contentType:'application/json',body:JSON.stringify({ok:scenario!=='failure',done:steps===3,limited:scenario==='limited',checked:steps,accepted:1,rejected:steps-1,sources_checked:1,sources_total:2})});}catch{}
    }else if(body.includes('search_ai_jobs')) return route.fulfill({contentType:'application/json',body:JSON.stringify({ok:true,search_id:'fixture-search'})});
    else throw new Error('Unexpected action');
   });
   await page.goto('http://jema.test/');await page.locator('button[value="search_ai_jobs"]').click();
   await page.waitForFunction(()=>document.querySelector('dialog')?.open);
   const box=await page.locator('dialog').boundingBox();assert(box.x>=8&&box.x+box.width<=382);
   await page.keyboard.press('Escape');await page.mouse.click(1,1);assert(await page.locator('dialog').evaluate(x=>x.open));
   while(!release)await new Promise(resolve=>setTimeout(resolve,10));
   if(scenario==='cancel') {
    await page.getByRole('button',{name:'Abbrechen',exact:true}).click();release();await page.waitForTimeout(150);
    assert.equal(steps,1);assert.equal(await page.locator('dialog').count(),0);
   }else {
    release();await page.getByRole('button',{name:'Ergebnisse anzeigen',exact:true}).waitFor();
    assert(await page.locator('dialog').evaluate(x=>x.open),'Completion does not close dialog');
    const status=await page.getByRole('status').innerText();
    assert.match(status,scenario==='failure'?/fehlgeschlagen/:scenario==='limited'?/nicht alle Möglichkeiten/:/Geprüft: 3.*Passend: 1.*Ausgeschlossen: 2.*abgeschlossen/);
    if(scenario==='success') {const out=path.join(process.env.TEMP||'/tmp','jema-search-dialog');fs.mkdirSync(out,{recursive:true});await page.screenshot({path:path.join(out,'complete.png')});}
   }
   assert.deepEqual(errors,[]);await page.close();console.log('PASS search dialog '+scenario);
  }
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
