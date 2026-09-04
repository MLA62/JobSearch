const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const fs = require('node:fs');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const html = execFileSync('php',['-n',path.join(__dirname,'job_import_dialog_fixture.php')],{encoding:'utf8'});
(async()=>{
 const browser=await chromium.launch({headless:true});
 try {
  for(const scenario of ['cancel','success','failure','commit-failure']){
   const page=await browser.newPage({viewport:{width:390,height:844}});
   let releasePrepare, releaseCommit, commits=0;
   const errors=[];page.on('pageerror',e=>errors.push(e.message));
   await page.route('http://jema.test/**',async route=>{
    const req=route.request();
    if(req.method()==='GET')return route.fulfill({contentType:'text/html',body:html});
    const body=req.postData()||'';
    if(body.includes('prepare_job_import')){
     await new Promise(resolve=>releasePrepare=resolve);
     try {await route.fulfill({status:scenario==='failure'?422:200,contentType:'application/json',body:JSON.stringify({ok:scenario!=='failure',token:'fixture-token'})});}catch{}
    }else if(body.includes('commit_job_import')){
     commits++;await new Promise(resolve=>releaseCommit=resolve);
     await route.fulfill({status:scenario==='commit-failure'?500:200,contentType:'application/json',body:JSON.stringify({ok:true,job_id:42})});
    }else throw new Error('Unexpected request');
   });
   await page.goto('http://jema.test/');
   await page.locator('button[value="prepare_ai_job_import"]').click();
   await page.waitForFunction(()=>document.querySelector('dialog').open);
   const box=await page.locator('dialog').boundingBox();assert(box.x>=8 && box.x+box.width<=382,'Dialog fits mobile viewport');
   assert.match(await page.locator('[data-import-status]').innerText(),/1\/2/);
   assert.match(await page.locator('[data-import-elapsed]').innerText(),/Vergangene Zeit/);
   await page.keyboard.press('Escape');assert.equal(await page.locator('dialog').evaluate(x=>x.open),true);
   await page.mouse.click(1,1);assert.equal(await page.locator('dialog').evaluate(x=>x.open),true);
   while(!releasePrepare)await new Promise(r=>setTimeout(r,10));
   if(scenario==='cancel'){
    const out=path.join(process.env.TEMP||'/tmp','jema-import-dialog');fs.mkdirSync(out,{recursive:true});
    await page.screenshot({path:path.join(out,'reading.png')});
    await page.locator('[data-import-cancel]').click();releasePrepare();
    assert.equal(await page.locator('dialog').evaluate(x=>x.open),false);
    await page.waitForTimeout(150);assert.equal(commits,0,'Cancellation sends no commit');
    assert.equal(await page.locator('button[value="prepare_ai_job_import"]').isEnabled(),true);
   }else{
    releasePrepare();
    if(scenario==='success'||scenario==='commit-failure'){
     await page.waitForFunction(()=>document.querySelector('[data-import-status]').textContent.includes('2/2'));
     assert.equal(await page.locator('[data-import-cancel]').isDisabled(),true);
     while(!releaseCommit)await new Promise(r=>setTimeout(r,10));releaseCommit();
     if(scenario==='success')await page.waitForURL('**/?page=jobs&edit=42#new');
     else {await page.waitForFunction(()=>document.querySelector('[data-import-status]').textContent.includes('nicht bestätigt'));assert.equal(await page.locator('dialog').evaluate(x=>x.open),true);await page.locator('[data-import-cancel]').click();}
     assert.equal(commits,1);
    }else{
     await page.waitForFunction(()=>document.querySelector('[data-import-status]').textContent.includes('fehlgeschlagen'));
     assert.equal(await page.locator('dialog').evaluate(x=>x.open),true);assert.equal(commits,0);
     await page.locator('[data-import-cancel]').click();
    }
   }
   assert.deepEqual(errors,[]);await page.close();console.log('PASS import dialog '+scenario);
  }
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
