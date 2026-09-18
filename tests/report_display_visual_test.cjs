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
      await page.goto(`${base}/tests/report_display_fixture.php?layout=1`);
      const savedTop = await page.locator('[data-layout-saved]').evaluate(node => node.getBoundingClientRect().top);
      const editorTop = await page.locator('[data-layout-editor]').evaluate(node => node.getBoundingClientRect().top);
      assert.equal(savedTop < editorTop, true, `saved reports precede editor at ${width}px`);
      for (const [type, selector] of Object.entries(expected)) {
        await page.goto(`${base}/tests/report_display_fixture.php?type=${type}`);
        assert.equal(await page.locator('#report-view').getAttribute('data-report-display-type'), type);
        assert.equal(await page.locator(selector).count() > 0, true, `${type} renderer visible`);
        assert.deepEqual(await page.locator('[data-report-view-option]').allTextContents(), ['Tabelle', 'Karten']);
        assert.equal(await page.locator('.report-view-filters').count(), 1, `${type} exposes report filters`);
        assert.equal(await page.locator('.report-view-filter-grid input[type="date"]').count(), 2, `${type} exposes date range filters`);
        assert.equal(await page.locator('.report-view-filter-grid input[type="number"]').count(), 2, `${type} exposes number range filters`);
        assert.equal(await page.locator('.report-view-choice-options input[type="checkbox"]').count(), 5, `${type} exposes multi-choice filters`);
        assert.equal(await page.locator('.report-view-filter-grid input[type="search"]').count(), 1, `${type} exposes text filters`);
        assert.equal(await page.locator('.report-record-link').count(), 0, `${type} has no separate record button`);
        assert.equal(await page.locator('.report-field-link').count() >= 13, true, `${type} links every non-empty field value`);
        const firstEntryLinks = type === 'table'
          ? page.locator('tbody tr').first().locator('.report-field-link')
          : page.locator('article.report-entry').first().locator('.report-field-link');
        assert.deepEqual((await firstEntryLinks.evaluateAll(nodes => nodes.slice(-4).map(node => new URL(node.href).pathname + new URL(node.href).search + new URL(node.href).hash))), [
          '/?page=applications&edit=101#application-form', '/?page=applications&edit=101#application-form', '/?page=applications&edit=101#application-form', '/?page=jobs&edit=11#new',
        ]);
        if (type === 'table') assert.equal((await page.locator('thead').innerText()).includes('Aktionen'), false, 'table has no actions column');
        assert.equal(await page.locator('#report-view').innerText().then(text => text.includes('<script>')), true, `${type} unsafe markup shown only as text`);
        assert.equal(await page.locator('script').count(), 0, `${type} did not execute data as HTML`);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2), true, `${type} ${width}px has no page overflow`);
        if (width === 390) await page.screenshot({ path: path.join(output, `${type}.png`) });
        if (type === 'cards') {
          await page.locator('.report-view-filters > summary').click();
          assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2), true, `expanded choice filter ${width}px has no page overflow`);
          if (width === 390) await page.screenshot({ path: path.join(output, 'cards-filters.png') });
        }
      }
      const choices = ['Noch nicht im Job-Room erfasst', 'Im Job-Room erfasst – Noch offen', 'Im Job-Room erfasst – Noch offen · Vorstellungsgespräch', 'Im Job-Room erfasst – Absage', '__empty__'];
      for (const choice of choices) {
        await page.goto(`${base}/tests/report_display_fixture.php?type=cards`);
        await page.locator('.report-view-filters > summary').click();
        await page.locator(`input[name="report_filter[job_room_result][values][]"][value="${choice}"]`).check();
        await page.locator('.report-view-filter-form button.primary').click();
        assert.equal(await page.locator('#report-view').getAttribute('data-report-display-type'), 'cards', 'filtering preserves card view');
        assert.equal(await page.locator('article.report-entry').count(), 1, `choice ${choice} returns exactly its semantic row`);
        assert.deepEqual(new URL(page.url()).searchParams.getAll('report_filter[job_room_result][values][]'), [choice], 'active filter is represented in the URL');
      }
      await page.goto(`${base}/tests/report_display_fixture.php?type=cards`);
      await page.locator('.report-view-filters > summary').click();
      for (const choice of ['Im Job-Room erfasst – Noch offen', 'Im Job-Room erfasst – Noch offen · Vorstellungsgespräch']) {
        await page.locator(`input[name="report_filter[job_room_result][values][]"][value="${choice}"]`).check();
      }
      await page.locator('.report-view-filter-form button.primary').click();
      assert.equal(await page.locator('article.report-entry').count(), 2, 'both open variants match with OR');
      assert.equal(await page.locator('.report-view-choice-options input:checked').count(), 2, 'multiple selections remain checked');
      await page.locator('[data-report-view-option="table"]').click();
      assert.equal(await page.locator('tbody tr').count(), 2, 'both selections survive switching to table');
      await page.locator('[data-report-view-option="cards"]').click();
      assert.equal(await page.locator('article.report-entry').count(), 2, 'both selections survive switching back to cards');
      const fieldCases = [
        ['input[name="report_filter[applied_at][from]"]', '2026-09-12', 3, 'date from'],
        ['input[name="report_filter[applied_at][to]"]', '2026-09-11', 1, 'date to'],
        ['input[name="report_filter[match_score][min]"]', '65', 3, 'number minimum'],
        ['input[name="report_filter[match_score][max]"]', '65', 2, 'number maximum'],
        ['input[name="report_filter[title][value]"]', 'verkaufs', 1, 'text contains'],
      ];
      for (const [selector, value, expectedCount, label] of fieldCases) {
        await page.goto(`${base}/tests/report_display_fixture.php?type=cards`);
        await page.locator('.report-view-filters > summary').click();
        await page.locator(selector).fill(value);
        await page.locator('.report-view-filter-form button.primary').click();
        assert.equal(await page.locator('article.report-entry').count(), expectedCount, `${label} works through the rendered form`);
      }
      await page.goto(`${base}/tests/report_display_fixture.php?type=cards`);
      await page.locator('.report-view-filters > summary').click();
      await page.locator('input[name="report_filter[applied_at][from]"]').fill('2026-09-12');
      await page.locator('input[name="report_filter[applied_at][to]"]').fill('2026-09-12');
      await page.locator('input[name="report_filter[match_score][min]"]').fill('65');
      await page.locator('input[name="report_filter[match_score][max]"]').fill('65');
      await page.locator('.report-view-filter-form button.primary').click();
      assert.equal(await page.locator('article.report-entry').count(), 1, 'date and number ranges combine correctly through the rendered form');
      await page.locator('[data-report-view-option="table"]').click();
      assert.equal(await page.locator('#report-view').getAttribute('data-report-display-type'), 'table');
      assert.equal(await page.locator('tbody tr').count(), 1, 'switching to table preserves active filters');
      await page.locator('[data-report-view-option="cards"]').click();
      assert.equal(await page.locator('#report-view').getAttribute('data-report-display-type'), 'cards');
      assert.equal(await page.locator('article.report-entry').count(), 1, 'switching back to cards preserves active filters');
    }
    assert.deepEqual(errors, []);
    console.log('PASS all report display types at mobile and desktop widths');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
