const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

const source = fs.readFileSync(path.join(__dirname, '..', 'public', 'index.php'), 'utf8');
const marker = "document.querySelectorAll('[data-document-version-select]')";
const markerAt = source.indexOf(marker);
const start = source.lastIndexOf('(() => {', markerAt);
const end = source.indexOf('\n})();', markerAt);
assert(start >= 0 && markerAt > start && end > markerAt, 'Document version script is present');
const script = source.slice(start, end + 6);

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.setContent(`
      <form>
        <select name="replace_document_id" data-document-version-select>
          <option value="0">New document</option>
          <option value="11" data-document-type-id="1" data-title="CV" data-language="de-CH"
            data-description="Current CV" data-valid-from="2026-01-01" data-valid-until=""
            data-application-relevant="1">CV v2</option>
          <option value="12" data-document-type-id="2" data-title="Private note" data-language="de-CH"
            data-description="Not for applications" data-valid-from="" data-valid-until=""
            data-application-relevant="0">Private note v1</option>
        </select>
        <select name="document_type_id"><option value="1">CV</option><option value="2">Other</option></select>
        <input name="document_title" value="">
        <select name="document_language"><option value="de-CH">Deutsch</option></select>
        <textarea name="document_description"></textarea>
        <input name="valid_from">
        <input name="valid_until">
        <input type="checkbox" name="is_application_relevant" value="1">
      </form>`);
    await page.addScriptTag({ content: script });

    const checkbox = page.locator('[name="is_application_relevant"]');
    assert.equal(await checkbox.isChecked(), false, 'new documents default to unchecked');

    await page.locator('[name="replace_document_id"]').selectOption('11');
    assert.equal(await checkbox.isChecked(), true, 'relevant current version hydrates as checked');
    assert.equal(await page.locator('[name="document_title"]').inputValue(), 'CV');

    await page.locator('[name="replace_document_id"]').selectOption('12');
    assert.equal(await checkbox.isChecked(), false, 'non-relevant current version hydrates as unchecked');

    await page.locator('[name="replace_document_id"]').selectOption('0');
    assert.equal(await checkbox.isChecked(), false, 'switching back restores the new-document default');
    assert.deepEqual(errors, []);
    console.log('PASS document relevance defaults and version inheritance');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
