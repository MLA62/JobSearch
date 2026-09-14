const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

const source = fs.readFileSync(path.join(__dirname, '..', 'public', 'index.php'), 'utf8');
const start = source.indexOf("(() => {\n    const richNames");
const end = source.indexOf('\n})();', start);
assert(start >= 0 && end > start, 'Rich-text editor script is present');
let editorScript = source.slice(start, end + 6);
editorScript = editorScript.replace(/<\?= json_encode\(richTextFieldNames\(\), JSON_UNESCAPED_UNICODE \| JSON_UNESCAPED_SLASHES\) \?>/, '["cover_letter_text"]');
editorScript = editorScript.replace(/<\?= json_encode\(substr\(\$appLocale, 0, 2\), JSON_UNESCAPED_UNICODE \| JSON_UNESCAPED_SLASHES\) \?>/, '"de"');
assert(!editorScript.includes('<?'), 'PHP placeholders were replaced');

const selectEditor = async (page) => page.evaluate(() => {
  const editor = document.querySelector('.rich-text-editor');
  const range = document.createRange();
  range.selectNodeContents(editor);
  const selection = window.getSelection();
  selection.removeAllRanges();
  selection.addRange(range);
});

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.setContent('<form><textarea name="cover_letter_text">&lt;p&gt;Alpha&lt;br&gt;&lt;strong&gt;Beta&lt;/strong&gt;&lt;/p&gt;&lt;p&gt;Gamma&lt;/p&gt;</textarea></form>');
    await page.addScriptTag({ content: editorScript });
    const toolbar = page.locator('.rich-text-toolbar');
    assert.equal(await toolbar.locator('button').nth(0).textContent(), '¶');
    assert.equal(await toolbar.locator('button').nth(1).textContent(), '↵');

    await selectEditor(page);
    await toolbar.locator('button').nth(1).click();
    assert.equal(await page.locator('.rich-text-editor').innerHTML(), '<p>Alpha<br><strong>Beta</strong><br>Gamma</p>');
    assert.equal(await page.locator('textarea').inputValue(), '<p>Alpha<br><strong>Beta</strong><br>Gamma</p>');

    await selectEditor(page);
    await toolbar.locator('button').nth(0).click();
    assert.equal(await page.locator('.rich-text-editor').innerHTML(), '<p>Alpha</p><p><strong>Beta</strong></p><p>Gamma</p>');
    assert.equal(await page.locator('textarea').inputValue(), '<p>Alpha</p><p><strong>Beta</strong></p><p>Gamma</p>');
    assert.deepEqual(errors, []);
    console.log('PASS selected paragraphs convert both ways and keep inline formatting');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
