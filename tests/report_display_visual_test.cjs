const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = process.env.JEMA_TEST_URL || 'http://127.0.0.1:8128';
const output = process.env.JEMA_TEST_OUTPUT || path.join(process.env.TEMP || '/tmp', 'jema-report-display');
require('node:fs').mkdirSync(output, { recursive: true });

const expected = {
  table: 'table',
  list: '.report-entries--list',
  cards: '.report-entries--cards',
  preview: '.report-entries--preview',
  calendar_day: '.report-calendar-groups--calendar_day',
  calendar_week: '.report-calendar-groups--calendar_week',
  calendar_month: '.report-calendar-groups--calendar_month',
};

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    for (const width of [390, 1366]) {
      await page.setViewportSize({ width, height: 900 });
      for (const [type, selector] of Object.entries(expected)) {
        await page.goto(`${base}/tests/report_display_fixture.php?type=${type}`);
        assert.equal(await page.locator('#report-view').getAttribute('data-report-display-type'), type);
        assert.equal(await page.locator(selector).count() > 0, true, `${type} renderer visible`);
        assert.equal(await page.locator('#report-view').innerText().then(text => text.includes('<script>')), true, `${type} unsafe markup shown only as text`);
        assert.equal(await page.locator('script').count(), 0, `${type} did not execute data as HTML`);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2), true, `${type} ${width}px has no page overflow`);
        if (width === 390) await page.screenshot({ path: path.join(output, `${type}.png`) });
      }
    }
    assert.deepEqual(errors, []);
    console.log('PASS all report display types at mobile and desktop widths');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
