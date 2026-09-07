/* Régénère les miniatures de buds à partir du modèle 3D.
   Usage : node tools/render-thumbs.js [id ...]   (sans argument : les 105 variétés)
   Nécessite playwright et un serveur local servant la racine du dépôt. */
const fs = require('fs');
const path = require('path');
const {chromium} = require('/opt/node22/lib/node_modules/playwright');

const ROOT = path.join(__dirname, '..');
const BASE = process.env.GP_BASE || 'http://127.0.0.1:8146';
const OUT = path.join(ROOT, 'assets/plantation/thumbs');

(async () => {
  const catalog = JSON.parse(fs.readFileSync(path.join(ROOT, 'assets/plantation/catalog.json'), 'utf8'));
  const wanted = process.argv.slice(2);
  const list = wanted.length ? catalog.filter(v => wanted.includes(v.id)) : catalog;

  const browser = await chromium.launch({args: ['--use-gl=swiftshader', '--enable-unsafe-swiftshader']});
  const page = await browser.newPage({viewport: {width: 420, height: 420}});
  page.on('pageerror', e => console.error('ERREUR PAGE', e.message));
  await page.goto(BASE + '/tools/render-thumbs.html', {waitUntil: 'networkidle'});
  await page.waitForFunction('window.gpReady === true');
  await page.waitForTimeout(1200); // laisse la photo de surface se charger

  let done = 0;
  for (const variety of list) {
    const data = await page.evaluate(v => window.gpThumb(v), variety);
    const file = path.join(OUT, variety.id + '-bud.webp');
    fs.writeFileSync(file, Buffer.from(data.split(',')[1], 'base64'));
    done++;
    if (done % 10 === 0 || done === list.length) console.log(`${done}/${list.length}`);
  }
  await browser.close();
})();
