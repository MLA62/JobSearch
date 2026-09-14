const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

const source = fs.readFileSync(path.join(__dirname, '..', 'public', 'index.php'), 'utf8');
const start = source.indexOf('/* import-clipboard-start */');
const endMarker = '/* import-clipboard-end */';
const end = source.indexOf(endMarker, start);
assert(start >= 0 && end > start, 'Quick-import clipboard script is present');
const clipboardScript = source.slice(start, end + endMarker.length);

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.setContent('<textarea name="import_payload" data-import-payload>Bereits vorhanden</textarea>');
    await page.addScriptTag({ content: clipboardScript });
    const prevented = await page.locator('textarea').evaluate((field) => {
      field.setSelectionRange(field.value.length, field.value.length);
      const transfer = new DataTransfer();
      transfer.setData('text/plain', 'Zur Arbeitgeber-Website\n\nJob merken');
      transfer.setData('text/html', '<div><a href="https://company.example/jobs">Zur Arbeitgeber-Website</a></div><div><a href="https://jobs.example/detail/42?x=1&amp;y=2">Job merken</a></div><a href="javascript:alert(1)">Unsicher</a>');
      const event = new ClipboardEvent('paste', { clipboardData: transfer, bubbles: true, cancelable: true });
      return !field.dispatchEvent(event);
    });
    const value = await page.locator('textarea').inputValue();
    assert.equal(prevented, true, 'Formatted paste is intercepted');
    assert(value.startsWith('Bereits vorhanden\n'));
    assert(value.includes('Zur Arbeitgeber-Website\nhttps://company.example/jobs'));
    assert(value.includes('Job merken\nhttps://jobs.example/detail/42?x=1&y=2'));
    assert(!value.includes('javascript:'));
    assert.equal((value.match(/https:\/\//g) || []).length, 2, 'Every unique safe target URL is retained once');

    await page.locator('textarea').fill('');
    const uriPrevented = await page.locator('textarea').evaluate((field) => {
      const transfer = new DataTransfer();
      transfer.setData('text/plain', 'Nur sichtbarer Linktext');
      transfer.setData('text/uri-list', '# Quelle\nhttps://jobs.example/detail/uri-only');
      const event = new ClipboardEvent('paste', { clipboardData: transfer, bubbles: true, cancelable: true });
      return !field.dispatchEvent(event);
    });
    assert.equal(uriPrevented, true, 'URI-list paste is intercepted when plain text lacks the target');
    assert.equal(await page.locator('textarea').inputValue(), 'Nur sichtbarer Linktext\nhttps://jobs.example/detail/uri-only');
    assert.deepEqual(errors, []);
    console.log('PASS formatted clipboard links retain their HTTPS targets in quick import');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
