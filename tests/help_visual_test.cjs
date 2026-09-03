const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const source = require('../docs/jobsearch/help/source.json');
const base = process.env.JEMA_TEST_URL || 'http://127.0.0.1:8128';
const output = process.env.JEMA_TEST_OUTPUT || path.join(process.env.TEMP || '/tmp', 'jema-help');
fs.mkdirSync(output, { recursive: true });

(async () => {
  const browser = await chromium.launch({ headless: true });
  let views = 0, contexts = 0;
  try {
    const page = await browser.newPage({ reducedMotion: 'reduce' });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    for (const locale of source.locales) {
      for (const width of [390, 1366, 2560]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto(base + '/tests/help_fixture.php?lang=' + locale);
        assert.equal(await page.locator('html').getAttribute('lang'), locale);
        assert.equal(await page.locator('.help-topic:visible').count(), 24);
        const ids = await page.locator('[id]').evaluateAll(elements => elements.map(el => el.id));
        assert.equal(ids.length, new Set(ids).size, 'Unique HTML IDs');
        assert(!/Warning:|Fatal error:|help\.v2\./.test(await page.locator('body').innerText()));
        for (const topic of source.topics) {
          const card = page.locator('#help-topic-' + topic.id);
          assert.equal(await card.locator('h2').innerText(), topic.text[locale].title);
          assert.deepEqual(await card.locator('ol li').allTextContents(), topic.text[locale].steps);
          assert.deepEqual(await card.locator('ul li').allTextContents(), topic.text[locale].tips);
          assert.equal(await card.locator('.actions a').count(), topic.links.length);
        }
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2), locale + ' ' + width + ' page overflow');
        const clipped = await page.locator('.help-topic h2,.help-topic li,.help-topic a,.help-filter-chips button').evaluateAll(elements => elements.filter(el => {
          const style = getComputedStyle(el);
          return style.textOverflow === 'ellipsis' || (['hidden','clip'].includes(style.overflowX) && el.scrollWidth > el.clientWidth + 2);
        }).map(el => el.textContent));
        assert.deepEqual(clipped, [], locale + ' ' + width + ' clipping');
        await page.locator('#help-search').fill('SMTP');
        assert(await page.locator('#help-topic-email').isVisible(), 'Procedure text is searchable');
        assert(await page.locator('.help-topic:visible').count() < 24);
        await page.locator('#help-search').fill('no-topic-7e9a1');
        assert.equal(await page.locator('.help-topic:visible').count(), 0);
        assert(await page.locator('#help-empty').isVisible());
        await page.locator('[data-help-reset]').click();
        assert.equal(await page.locator('.help-topic:visible').count(), 24);
        await page.locator('[data-help-chip]').first().click();
        assert(await page.locator('.help-topic.is-highlighted').count() > 0);
        await page.locator('[data-help-reset]').click();
        await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
        await page.screenshot({ path: path.join(output, locale + '-' + width + '.png') });
        views++;
      }
      await page.setViewportSize({ width: 390, height: 844 });
      for (const topic of source.topics) {
        for (const context of topic.pages) {
          await page.goto(base + '/tests/help_fixture.php?lang=' + locale + '&context=' + context);
          await page.locator('[data-context-help-open]').click();
          const dialog = page.getByRole('dialog');
          assert(await dialog.isVisible());
          assert.equal(await page.locator('#context-help-title').innerText(), topic.text[locale].title);
          assert.deepEqual(await dialog.locator('ol li').allTextContents(), topic.text[locale].steps);
          const bounds = await dialog.boundingBox();
          assert(bounds.x >= 0 && bounds.x + bounds.width <= 392, context + ' dialog overflow');
          assert.equal(await dialog.locator('a').first().getAttribute('href'), '/?page=help#help-topic-' + topic.id);
          if (context === 'applications') await page.screenshot({ path: path.join(output, 'context-' + locale + '.png') });
          await page.keyboard.press('Escape');
          assert.equal(await dialog.isVisible(), false);
          assert(await page.locator('[data-context-help-open]').evaluate(el => el === document.activeElement));
          contexts++;
        }
      }
      console.log('PASS ' + locale + ': all topics, search, categories, context dialogs');
    }
    assert.deepEqual(errors, []);
    console.log('PASS ' + views + ' help views and ' + contexts + ' context dialogs');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
