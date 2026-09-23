const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

const source = fs.readFileSync(path.join(__dirname, '..', 'public', 'index.php'), 'utf8');
const css = fs.readFileSync(path.join(__dirname, '..', 'public', 'assets', 'app.css'), 'utf8');
const start = source.indexOf("(() => {\n    const richNames");
const end = source.indexOf('\n})();', start);
assert(start >= 0 && end > start, 'Rich-text editor script is present');
let editorScript = source.slice(start, end + 6);
editorScript = editorScript.replace(/<\?= json_encode\(richTextFieldNames\(\), JSON_UNESCAPED_UNICODE \| JSON_UNESCAPED_SLASHES\) \?>/, '["cover_letter_text"]');
editorScript = editorScript.replace(/<\?= json_encode\(substr\(\$appLocale, 0, 2\), JSON_UNESCAPED_UNICODE \| JSON_UNESCAPED_SLASHES\) \?>/, '"de"');
assert(!editorScript.includes('<?'), 'PHP placeholders were replaced');

async function selectContents(page, selector) {
  await page.evaluate((selected) => {
    const range = document.createRange();
    range.selectNodeContents(document.querySelector(selected));
    const selection = window.getSelection();
    selection.removeAllRanges(); selection.addRange(range);
    document.dispatchEvent(new Event('selectionchange'));
  }, selector);
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.setContent('<form><label>Motivationsschreiben<textarea name="cover_letter_text">&lt;p&gt;Alpha&lt;br&gt;Beta&lt;/p&gt;&lt;p&gt;Gamma&lt;/p&gt;&lt;ul&gt;&lt;li&gt;Eintrag&lt;/li&gt;&lt;li&gt;Zweiter&lt;/li&gt;&lt;/ul&gt;</textarea></label></form>');
    await page.addStyleTag({ content: css });
    await page.addScriptTag({ content: editorScript });
    const editor = page.locator('.rich-text-editor');
    const toolbar = page.locator('.rich-text-toolbar');
    const format = toolbar.getByRole('combobox', { name: 'Absatzformat' });
    assert.deepEqual(await format.locator('option').allTextContents(), ['Absatz', 'H1', 'H2', 'H3']);
    assert.equal(await toolbar.getByRole('button', { name: 'Markierte Zeilen in Absätze umwandeln' }).count(), 0);

    const before = await editor.innerHTML();
    await editor.locator('p').first().click();
    assert.equal(await editor.innerHTML(), before, 'Clicking a paragraph does not insert a line');
    assert.equal(await page.evaluate(() => document.activeElement?.className), 'rich-text-editor', 'Paragraph click keeps focus in the editor');
    await editor.locator('li').first().click();
    assert.equal(await editor.innerHTML(), before, 'Clicking a bullet does not change its structure');
    assert.equal(await page.evaluate(() => document.activeElement?.className), 'rich-text-editor', 'Bullet click keeps focus in the editor');
    const metrics = await page.evaluate(() => {
      const root = document.querySelector('.rich-text-editor');
      const read = selector => { const style = getComputedStyle(root.querySelector(selector)); return [style.fontSize, style.marginTop, style.marginBottom]; };
      return { p: read('p'), ul: read('ul'), li: read('li') };
    });
    assert.equal(metrics.p[0], '16px', 'Paragraphs have a 12pt font');
    assert.equal(metrics.p[2], '12px', 'Paragraphs have 9pt trailing space, half of the 18pt line box');
    assert.equal(metrics.li[2], '0px', 'List items have no extra line gap');

    await selectContents(page, '.rich-text-editor p:first-child');
    await format.selectOption('h1');
    assert.match(await editor.innerHTML(), /^<h1>Alpha<br>Beta<\/h1>/);
    const heading = await page.evaluate(() => { const style = getComputedStyle(document.querySelector('.rich-text-editor h1')); return [style.fontSize, style.marginTop, style.marginBottom]; });
    assert.deepEqual(heading, ['24px', '32px', '12px'], 'H1 uses 18pt / 24pt before / 9pt after');

    await selectContents(page, '.rich-text-editor h1');
    await toolbar.locator('button[title="Formatierung löschen"]').click();
    assert.match(await editor.innerHTML(), /^<p>Alpha<br>Beta<\/p>/, 'Tx resets the heading to a paragraph');
    await selectContents(page, '.rich-text-editor p:first-child');
    await format.selectOption('h2');
    assert.match(await editor.innerHTML(), /^<h2>Alpha<br>Beta<\/h2>/);
    const h2 = await page.evaluate(() => { const s=getComputedStyle(document.querySelector('.rich-text-editor h2')); return [s.fontSize,s.marginTop,s.marginBottom]; });
    assert.deepEqual(h2, ['21.3333px','16px','12px'], 'H2 has 16pt font and 12pt / 9pt margins');
    await selectContents(page, '.rich-text-editor h2');
    await format.selectOption('h3');
    assert.match(await editor.innerHTML(), /^<h3>Alpha<br>Beta<\/h3>/);
    const h3 = await page.evaluate(() => { const s=getComputedStyle(document.querySelector('.rich-text-editor h3')); return [s.fontSize,s.marginTop,s.marginBottom]; });
    assert.deepEqual(h3, ['18.6667px','16px','12px'], 'H3 has 14pt font and 12pt / 9pt margins');
    await selectContents(page, '.rich-text-editor h3');
    await format.selectOption('p');
    assert.match(await editor.innerHTML(), /^<p>Alpha<br>Beta<\/p>/);

    await editor.locator('p').nth(1).click();
    await page.keyboard.press('End');
    await page.keyboard.press('Shift+Enter');
    await page.keyboard.type('Delta');
    assert.match(await editor.innerHTML(), /<p>Gamma<br>Delta<\/p>/, 'Shift+Enter creates a soft break inside the paragraph');
    await page.keyboard.press('Enter');
    await page.keyboard.type('Epsilon');
    assert.match(await editor.innerHTML(), /<p>Gamma<br>Delta<\/p><p>Epsilon<\/p>/, 'Enter creates a new paragraph');
    assert.equal(await page.locator('textarea').inputValue(), await editor.innerHTML(), 'Form value matches visible editor');

    await selectContents(page, '.rich-text-editor p:first-child');
    await toolbar.locator('button[title="Fett"]').click();
    assert.match(await editor.innerHTML(), /<(?:b|strong)>Alpha<br>Beta<\/(?:b|strong)>/);
    await selectContents(page, '.rich-text-editor p:first-child');
    await toolbar.locator('button[title="Formatierung löschen"]').click();
    assert.doesNotMatch(await editor.locator('p').first().innerHTML(), /<(?:strong|b|span|a|em)\b/i, 'Tx removes inline formatting');

    await editor.locator('li').first().click();
    await page.keyboard.press('End');
    await page.keyboard.press('Enter');
    await page.keyboard.type('Neu');
    assert.match(await editor.innerHTML(), /<ul><li>Eintrag<\/li><li>Neu<\/li><li>Zweiter<\/li><\/ul>/, 'Enter inside a bullet creates a new bullet without a paragraph gap');

    await page.locator('textarea').evaluate((textarea) => {
      textarea.value = '<p><a href="https://example.org"><em>Link</em></a></p><h1>Titel</h1>';
      textarea.dispatchEvent(new Event('jema:richtext-load'));
    });
    await selectContents(page, '.rich-text-editor p');
    await toolbar.locator('button[title="Formatierung löschen"]').click();
    assert.equal(await editor.locator('p').first().innerHTML(), 'Link', 'Tx removes links and italic formatting');
    await editor.locator('h1').click();
    await page.keyboard.press('End');
    await page.keyboard.press('Enter');
    await page.keyboard.type('Weiter');
    assert.match(await editor.innerHTML(), /<h1>Titel<\/h1><p>Weiter<\/p>/, 'Enter after a heading starts a normal paragraph');

    await page.locator('textarea').evaluate((textarea) => {
      textarea.value = '<p>Erster</p><p>Zweiter</p>';
      textarea.dispatchEvent(new Event('jema:richtext-load'));
    });
    await selectContents(page, '.rich-text-editor');
    await format.selectOption('h2');
    assert.equal(await editor.innerHTML(), '<h2>Erster</h2><h2>Zweiter</h2>', 'Selecting two paragraphs formats both without inserting content');
    await editor.locator('h2').first().click();
    await page.keyboard.press('End');
    await format.selectOption('h3');
    await page.keyboard.type('!');
    assert.equal(await editor.innerHTML(), '<h3>Erster!</h3><h2>Zweiter</h2>', 'Formatting at a caret preserves the insertion point and the next paragraph');

    await page.locator('textarea').evaluate((textarea) => {
      textarea.value = '<ul><li><strong>Eins</strong></li><li>Zwei</li></ul>';
      textarea.dispatchEvent(new Event('jema:richtext-load'));
    });
    await selectContents(page, '.rich-text-editor li:first-child');
    await toolbar.locator('button[title="Formatierung löschen"]').click();
    assert.match(await editor.innerHTML(), /^<p>Eins<\/p><ul><li>Zwei<\/li><\/ul>$/, 'Tx clears the selected bullet without changing the next one');

    assert.deepEqual(errors, []);
    console.log('PASS paragraph styles, keyboard breaks, list spacing, click stability and clear formatting');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
