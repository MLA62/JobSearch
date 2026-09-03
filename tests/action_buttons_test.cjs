const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');
const languages = {
  'de-CH': ['Aktivit\u00e4t speichern', 'Bearbeiten', 'L\u00f6schen'],
  'fr-CH': ['Enregistrer l\u2019activit\u00e9', 'Modifier', 'Supprimer'],
  'en-GB': ['Save activity', 'Edit', 'Delete'],
  'pt-BR': ['Salvar atividade', 'Editar', 'Excluir'],
  'es-MX': ['Guardar actividad', 'Editar', 'Eliminar'],
};
(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    const css = execFileSync('git', ['show', 'HEAD:public/assets/app.css'], { cwd: root, encoding: 'utf8' }) + fs.readFileSync(path.join(root, 'public/assets/layout.css'), 'utf8');
    for (const [locale, [save, edit, remove]] of Object.entries(languages)) {
      for (const width of [390, 1000, 2048]) {
        await page.setViewportSize({ width, height: 800 });
        await page.setContent(`<html lang="${locale}"><style>${css}</style><main class="container">
          <section class="panel"><form class="stack" onsubmit="event.preventDefault();this.dataset.saved='yes'">
          <div class="actions"><button class="primary">${save}</button><button disabled>${remove}</button></div></form></section>
          <section class="panel"><div class="section-head"><h2>Profile</h2><a href="#edit">${edit}</a></div></section>
          <section class="panel table-wrap"><table><thead><tr><th>Job</th><th>Actions</th></tr></thead>
          <tbody><tr><td>Job</td><td class="actions"><a href="#edit">${edit}</a><form><button>${remove}</button></form></td></tr></tbody></table></section>
          </main></html>`);
        await page.addScriptTag({ path: path.join(root, 'public/assets/layout.js') });
        const errors = await page.locator('.actions button, .actions a, .section-head > a').evaluateAll(elements => elements.flatMap(el => {
          const style = getComputedStyle(el), rect = el.getBoundingClientRect();
          const parent = el.closest('td') || el.closest('.panel');
          const bounds = parent.getBoundingClientRect();
          return style.borderTopColor === 'transparent' || style.borderTopColor === 'rgba(0, 0, 0, 0)' || parseFloat(style.borderTopWidth) < 1 || el.scrollWidth > el.clientWidth + 1 || rect.right > bounds.right + 1
            ? [el.textContent] : [];
        }));
        assert.deepEqual(errors, [], `${locale} ${width}: visible, contained buttons`);
        await page.locator('button.primary').click();
        assert.equal(await page.locator('form.stack').getAttribute('data-saved'), 'yes');
        assert.equal(await page.locator('button[disabled]').isDisabled(), true);
        if (locale === 'de-CH') await page.screenshot({ path: path.join(process.env.TEMP || '/tmp', `jobsearch-actions-${width}.png`) });
        console.log(`PASS ${locale} ${width}: borders, text, containment, submit, disabled`);
      }
    }
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
