const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public', 'index.php'), 'utf8');
const normalizedSource = source.replace(/\r\n/g, '\n');
const scriptStart = normalizedSource.indexOf("(() => {\n    const dialog = document.getElementById('ai-work-dialog');");
const scriptEnd = normalizedSource.indexOf("(() => {\n    document.querySelectorAll('[data-file-picker]')", scriptStart);
assert.ok(scriptStart > 0 && scriptEnd > scriptStart, 'production AI browser script found');
const productionScript = normalizedSource.slice(scriptStart, scriptEnd);
const css = fs.readFileSync(path.join(root, 'public', 'assets', 'app.css'), 'utf8') + '\n' + fs.readFileSync(path.join(root, 'public', 'assets', 'layout.css'), 'utf8');

const html = `<!doctype html><html><head><meta name="viewport" content="width=device-width"><style>${css}</style></head><body class="admin-ai-screen">
<header class="topbar"><div class="topbar-inner"><a class="brand">JeMa Jobs</a></div></header>
<main class="container"><div class="admin-ai-page"><div class="page-head"><div><p class="eyebrow">KONTO</p><h1>KI für Plattformoperationen</h1></div><span>GPT-5.6 Luna<br>0 Kontexte gespeichert</span></div><section class="panel admin-ai-console">
  <div class="admin-ai-output-wrap"><span>Ausgabe</span><div class="admin-ai-output" data-admin-ai-output data-empty-placeholder="Noch keine Ausgabe"></div></div>
  <form method="post" class="stack admin-ai-form" data-admin-ai-form>
    <input type="hidden" name="csrf" value="test">
    <label>Anweisung<textarea class="admin-ai-input" name="admin_ai_instruction" required></textarea></label>
    <div class="actions"><button class="primary" name="action" value="admin_ai_request">KI ausführen</button><button name="action" value="admin_ai_clear_memory" formnovalidate>Gedächtnis löschen</button></div>
  </form>
</section></div></main><footer>JeMa Jobs</footer>
<dialog id="ai-work-dialog"><h2>In Arbeit</h2><button type="button" data-ai-work-abort>Abbrechen</button></dialog>
</body></html>`;

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    let requestCount = 0;
    const requestUrls = [];
    await page.route('https://jema.test/**', async route => {
      if (route.request().isNavigationRequest()) {
        await route.fulfill({ status: 200, body: html, contentType: 'text/html' });
        return;
      }
      requestCount += 1;
      requestUrls.push(route.request().url());
      if (route.request().url() !== 'https://jema.test/?page=admin_ai') {
        await route.fulfill({ status: 404, body: 'Not found', contentType: 'text/plain' });
        return;
      }
      const response = requestCount === 1
        ? { status: 422, body: JSON.stringify({ ok: false, error: 'OpenAI HTTP 400: test detail', output: '[FEHLER] OpenAI HTTP 400: test detail', instruction: 'Firma Cleeven erfassen', context_count: 1 }) }
        : { status: 200, body: JSON.stringify({ ok: true, output: '[DB] **Erfasst**: Firma Cleeven', instruction: 'Firma Cleeven erfassen', context_count: 2 }) };
      await route.fulfill({ ...response, contentType: 'application/json' });
    });
    await page.goto('https://jema.test/?page=admin_ai');
    await page.addScriptTag({ content: productionScript });

    const input = page.locator('[name="admin_ai_instruction"]');
    const output = page.locator('[data-admin-ai-output]');
    await input.fill('Firma Cleeven erfassen');
    await page.locator('button[value="admin_ai_request"]').click();
    await page.waitForFunction(() => document.querySelector('[data-admin-ai-output]')?.textContent.includes('OpenAI HTTP 400'));
    assert.equal(await input.inputValue(), 'Firma Cleeven erfassen', 'instruction survives structured HTTP 422');
    assert.match(await output.textContent(), /OpenAI HTTP 400: test detail/, 'specific server error stays visible');
    assert.equal(await page.locator('#ai-work-dialog').getAttribute('open'), null, 'work dialog closes after handled error');
    assert.equal(await page.evaluate(() => sessionStorage.getItem('jema-admin-ai-draft')), 'Firma Cleeven erfassen', 'browser draft retained');

    await page.locator('button[value="admin_ai_request"]').click();
    await page.waitForFunction(() => document.querySelector('[data-admin-ai-output] strong')?.textContent === 'Erfasst');
    assert.equal(await input.inputValue(), 'Firma Cleeven erfassen', 'instruction survives success');
    assert.equal(await output.locator('strong').textContent(), 'Erfasst', 'Markdown output rendered');
    assert.equal(requestCount, 2, 'both request paths executed');
    assert.deepEqual(requestUrls, ['https://jema.test/?page=admin_ai', 'https://jema.test/?page=admin_ai'], 'unnamed form action posts to the current production page despite action-named buttons');
    for (const viewport of [{ width: 390, height: 800 }, { width: 1366, height: 768 }, { width: 2048, height: 1080 }]) {
      await page.setViewportSize(viewport);
      const layout = await page.evaluate(() => {
        const output = document.querySelector('[data-admin-ai-output]').getBoundingClientRect();
        const input = document.querySelector('[name="admin_ai_instruction"]').getBoundingClientRect();
        return { pageHeight: document.documentElement.scrollHeight, viewportHeight: window.innerHeight, output, input };
      });
      assert.ok(layout.pageHeight <= layout.viewportHeight + 1, `${viewport.width}x${viewport.height}: no page scroll`);
      assert.ok(layout.output.top >= 0 && layout.output.bottom <= layout.viewportHeight, `${viewport.width}x${viewport.height}: output visible`);
      assert.ok(layout.input.top >= 0 && layout.input.bottom <= layout.viewportHeight, `${viewport.width}x${viewport.height}: input visible`);
    }
    console.log('PASS admin AI console: input retention, HTTP 422 detail, success, Markdown, modal lifecycle');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
