const assert = require('node:assert/strict');
const path = require('node:path');
const fs = require('node:fs');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = process.env.JEMA_TEST_URL || 'http://127.0.0.1:8127';
const output = process.env.JEMA_TEST_OUTPUT || path.join(process.env.TEMP, 'jema-workflow-v6');
fs.mkdirSync(output, { recursive: true });
(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    for (const locale of ['de-CH', 'fr-CH', 'en-GB', 'pt-BR', 'es-MX']) {
      for (const width of [390, 1000, 2048, 3800]) {
        await page.setViewportSize({ width, height: 1000 });
        for (const view of ['table', 'cards']) {
          await page.goto(`${base}/tests/workflow_v6_fixture.php?lang=${locale}&view=${view}`);
          assert.equal(await page.locator('html').getAttribute('lang'), locale);
          assert.equal(await page.locator('[data-sent-date]').isVisible(), false);
          await page.locator('select[name=status]').selectOption('sent');
          assert.equal(await page.locator('[data-sent-date]').isVisible(), true);
          await page.locator('select[name=status]').selectOption('ready');
          assert.equal(await page.locator('[data-sent-date]').isVisible(), false);
          assert.equal(await page.locator('.job-room-details').isVisible(), false);
          await page.locator('[data-job-room-recorded]').check();
          assert.equal(await page.locator('.job-room-details').isVisible(), true);
          await page.locator('[data-job-room-recorded]').uncheck();
          if (view === 'table') {
            assert.equal(await page.locator('table thead th').count(), 6);
            assert.equal(await page.locator('table tbody tr').count(), 6);
            assert.equal(await page.locator('table input[type=checkbox]').count(), 0);
            assert.equal(await page.locator('table table').count(), 0);
            const rows = await page.locator('table tbody tr').evaluateAll(rows => rows.map(row => ({
              cells: row.cells.length, display: getComputedStyle(row).display, date: row.cells[0].textContent.trim(),
            })));
            assert(rows.every(row => row.cells === 6 && row.display === 'table-row' && row.date === '10.09.2026'));
          } else {
            assert.equal(await page.locator('.application-card').count(), 6);
            assert.equal(await page.locator('.application-card').filter({ hasText: 'next_action' }).count(), 0);
          }
          const clipped = await page.locator('.layout-table th, .layout-table td, .layout-table a, .application-card h3, .application-card .button').evaluateAll(elements => elements.filter(el => {
            const style = getComputedStyle(el);
            return (['hidden', 'clip'].includes(style.overflowX) && el.scrollWidth > el.clientWidth + 2) || style.textOverflow === 'ellipsis';
          }).map(el => el.textContent));
          assert.deepEqual(clipped, [], `${locale} ${width} ${view}: no clipping`);
          assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2), true, `${width}: only table may scroll`);
          assert(!/Warning:|Fatal error:/.test(await page.locator('body').innerText()));
          if (locale === 'de-CH') await page.screenshot({ path: path.join(output, `${view}-${width}.png`), fullPage: true });
          console.log(`PASS ${locale} ${width} ${view}`);
        }
      }
    }
    assert.deepEqual(errors, []);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
