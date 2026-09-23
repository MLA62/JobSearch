const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

const css = fs.readFileSync(path.join(__dirname, '..', 'public', 'assets', 'app.css'), 'utf8');
const outline = fs.readFileSync(path.join(__dirname, '..', 'public', 'assets', 'data', 'switzerland-outline.path'), 'utf8').trim();
const chart = (title, gradient) => `<article class="panel dashboard-chart-card"><header class="dashboard-chart-head"><div><h2>${title}</h2><p>Anteile nach Status</p></div><strong>12</strong></header><div class="dashboard-chart-body"><div class="dashboard-pie" style="--dashboard-pie:${gradient}"></div><ul class="dashboard-chart-legend"><li><a class="dashboard-chart-link" href="/?page=jobs&sf_filter=open"><span class="dashboard-chart-swatch" style="--segment-color:#c2410c"></span><span>Offen</span><strong>8 · 67%</strong></a></li><li><a class="dashboard-chart-link" href="/?page=jobs&sf_filter=interview"><span class="dashboard-chart-swatch" style="--segment-color:#1d4ed8"></span><span>Gespräch</span><strong>4 · 33%</strong></a></li></ul></div></article>`;
const places = Array.from({ length: 24 }, (_, index) => `<li><a href="/?page=companies&sf_filter=Ort${index + 1}"><span>Ort ${index + 1}</span><strong>${24 - index}</strong></a></li>`).join('');
const html = `<main class="container"><section class="dashboard-charts">${chart('Jobs','conic-gradient(#c2410c 0 67%,#1d4ed8 67% 100%)')}${chart('Firmen','conic-gradient(#047857 0 50%,#7c3aed 50% 100%)')}${chart('Bewerbungen','conic-gradient(#be123c 0 25%,#0f766e 25% 100%)')}</section><section class="panel dashboard-heatmap"><header class="dashboard-chart-head"><div><h2>Firmenkonzentration Schweiz</h2><p>Grössere Kreise zeigen mehr Firmen.</p></div><strong>12</strong></header><div class="dashboard-heatmap-body"><svg class="dashboard-swiss-map" viewBox="0 0 1000 640"><path class="dashboard-swiss-outline" d="${outline}"/><a class="dashboard-heat-link" href="/?page=companies&sf_filter=Bern"><circle class="dashboard-heat-point" cx="340" cy="280" r="34" opacity=".9"/></a><a class="dashboard-heat-link" href="/?page=companies&sf_filter=Zürich"><circle class="dashboard-heat-point" cx="590" cy="120" r="20" opacity=".6"/></a></svg><ol class="dashboard-heat-list">${places}</ol></div></section></main>`;

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const viewport of [{ width: 1440, height: 1000, columns: 3 }, { width: 390, height: 1100, columns: 1 }]) {
      const page = await browser.newPage({ viewport });
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      await page.setContent(html);
      await page.addStyleTag({ content: css });
      const columns = (await page.locator('.dashboard-charts').evaluate(node => getComputedStyle(node).gridTemplateColumns)).split(' ').length;
      assert.equal(columns, viewport.columns, `chart grid uses ${viewport.columns} column(s) at ${viewport.width}px`);
      assert.match(await page.locator('.dashboard-pie').first().evaluate(node => getComputedStyle(node).backgroundImage), /conic-gradient/);
      assert.equal(await page.locator('.dashboard-chart-card').count(), 3);
      assert.equal(await page.locator('.stats').count(), 0);
      assert.equal(await page.locator('.dashboard-chart-link').count(), 6);
      assert.equal(await page.locator('.dashboard-swiss-outline').count(), 1);
      assert.equal(await page.locator('.dashboard-heat-link').count(), 2);
      assert.equal(await page.locator('.dashboard-heat-list a').count(), 24);
      const placeList = await page.locator('.dashboard-heat-list').evaluate(node => ({ clientHeight: node.clientHeight, scrollHeight: node.scrollHeight, overflowY: getComputedStyle(node).overflowY }));
      assert.equal(placeList.overflowY, 'auto');
      assert.ok(placeList.scrollHeight > placeList.clientHeight, 'all places remain available through a vertical scrollbar');
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
      assert.ok(overflow <= 1, `no horizontal overflow at ${viewport.width}px`);
      assert.deepEqual(errors, []);
      await page.close();
    }
    console.log('PASS dashboard pies and Switzerland heat map render responsively');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exit(1); });
