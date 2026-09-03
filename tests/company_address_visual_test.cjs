const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    for (const width of [390, 1000, 2048]) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto('http://127.0.0.1:8127/tests/table_fixture.php');
      const table = page.locator('[data-bulk-action="bulk_delete_companies"] table');
      assert.equal(await table.locator('input[type=checkbox]').count(), 0);
      const address = table.locator('tbody tr').first().locator('td').nth(1);
      assert.match(await address.innerText(), /Teststrasse 23\s+4500 Solothurn\s+\+41 32 000 00 00/);
      const lines = await address.locator('small').evaluateAll(nodes => nodes.map(node => {
        const css = getComputedStyle(node);
        return { top: node.getBoundingClientRect().top, display: css.display,
          whitespace: css.whiteSpace, overflow: css.overflowX, ellipsis: css.textOverflow };
      }));
      assert.equal(lines.length, 3);
      assert(lines.every(line => line.display === 'block' && line.whitespace === 'normal' && line.overflow === 'visible' && line.ellipsis !== 'ellipsis'));
      assert(lines[0].top < lines[1].top && lines[1].top < lines[2].top);
      await page.screenshot({ path: path.join(process.env.TEMP, `jema-company-address-${width}.png`), fullPage: true });
      console.log(`PASS company address wrapping ${width}`);
    }
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
