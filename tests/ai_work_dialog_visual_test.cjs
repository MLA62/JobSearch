const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

const source = fs.readFileSync(path.join(__dirname, '../public/index.php'), 'utf8');
const modal = source.match(/<dialog id="ai-work-dialog"[\s\S]*?<\/dialog>/)?.[0]
  .replace(/<\?= [\s\S]*? \?>/g, 'KI wird ausgeführt');
let script = source.match(/<script>\s*(\(\(\) => \{\s*const dialog = document\.getElementById\('ai-work-dialog'\);[\s\S]*?\n\}\)\(\);)\s*\/\* import-clipboard-start \*\//)?.[1];
assert(modal && script, 'The production modal and its handler must be extractable');
script = script.replace(/const statusLabels = <\?= json_encode\([\s\S]*?\) \?>;/,
  'const statusLabels = {suggest_job_search_criteria:"Suchkriterien werden verarbeitet",response:"Antwort wird verarbeitet",admin_ai_request:"Anfrage wird verarbeitet"};');
assert(!script.includes('<?='), 'No PHP interpolation remains in the tested script');
const html = `<!doctype html><html><body><form action="http://jema.test/" method="post"><button name="action" value="suggest_job_search_criteria">KI</button></form>${modal}<script>${script}</script></body></html>`;

(async () => {
  const browser = await chromium.launch({headless:true});
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('http://jema.test/**', async route => {
      if (route.request().method() === 'GET') return route.fulfill({contentType:'text/html',body:html});
      // Keep the AI request in flight until the user aborts it.
      await new Promise(resolve => page.once('close', resolve));
    });
    await page.goto('http://jema.test/');
    await page.locator('button[value="suggest_job_search_criteria"]').click();
    await page.waitForFunction(() => document.querySelector('#ai-work-dialog').open);
    assert.equal(await page.locator('[data-ai-work-phase]').innerText(), 'Suchkriterien werden verarbeitet');
    assert.equal(await page.locator('[data-ai-work-elapsed]').innerText(), '00:00');
    await page.waitForFunction(() => document.querySelector('[data-ai-work-elapsed]').textContent === '00:01', {timeout:2500});
    await page.locator('[data-ai-work-abort]').click();
    assert.equal(await page.locator('#ai-work-dialog').evaluate(dialog => dialog.open), false);
    await page.waitForTimeout(1200);
    assert.equal(await page.locator('[data-ai-work-elapsed]').innerText(), '00:01', 'Aborting stops the elapsed counter');
    assert.deepEqual(errors, []);
    await page.close();
    console.log('PASS actual production AI dialog updates each second and cleans up on abort');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
