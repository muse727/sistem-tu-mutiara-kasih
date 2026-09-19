const puppeteer = require('puppeteer-core');
const BASE = 'https://0cff-140-213-9-252.ngrok-free.app';
const sleep = ms => new Promise(r=>setTimeout(r,ms));
(async () => {
  const browser = await puppeteer.launch({ executablePath: '/usr/bin/google-chrome', headless: 'new', args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewport({ width: 390, height: 844 });
  await page.setExtraHTTPHeaders({ 'ngrok-skip-browser-warning': '1' });

  await page.goto(BASE + '/__e2e-login-siswa', { waitUntil: 'networkidle0' });
  await sleep(1500);
  await page.evaluate(() => initBayar(5, 'SPP E2E', 50000));
  await sleep(700);
  await page.evaluate(() => {
    const sheet = document.querySelector('[x-data*="billId"]');
    const btn = [...sheet.querySelectorAll('button')].find(b => (b.getAttribute('@click')||'').includes('prosesPayment'));
    if (btn) btn.click();
  });
  await sleep(6000);

  const snapFrame = page.frames().find(f => f.url().includes('snap') && f.url().includes('popup'));
  if (!snapFrame) { console.log('NO SNAP FRAME'); await browser.close(); return; }
  console.log('snap frame found, selecting QRIS...');

  // list clickable payment options in snap
  const opts = await snapFrame.evaluate(() => {
    const els = [...document.querySelectorAll('a,button,div[role=button],li,.list-item,[class*=payment]')];
    return els.filter(e=>/QRIS|qris/i.test(e.textContent||'')).map(e=>({tag:e.tagName,cls:(e.className||'').toString().slice(0,30),txt:(e.textContent||'').trim().slice(0,20)})).slice(0,5);
  });
  console.log('QRIS options:', JSON.stringify(opts));

  // click QRIS
  const clicked = await snapFrame.evaluate(() => {
    const els = [...document.querySelectorAll('a,button,div,li')];
    const q = els.find(e => /QRIS/i.test(e.textContent||'') && (e.textContent||'').trim().length < 25);
    if (q) { q.click(); return 'clicked:'+ (q.textContent||'').trim().slice(0,20); }
    return 'not-found';
  });
  console.log('QRIS click:', clicked);
  await sleep(2500);
  await page.screenshot({ path: '/tmp/snap-after-qris.png' });
  console.log('screenshot saved');

  // look for a pay/continue button
  const payBtn = await snapFrame.evaluate(() => {
    const els = [...document.querySelectorAll('button,a')];
    const b = els.find(e => /bayar|pay|continue|lanjut/i.test(e.textContent||''));
    if (b) { b.click(); return 'clicked:'+(b.textContent||'').trim().slice(0,20); }
    return 'none';
  });
  console.log('pay button:', payBtn);
  await sleep(3000);
  await page.screenshot({ path: '/tmp/snap-final.png' });
  const frameTxt = await snapFrame.evaluate(() => (document.body.innerText||'').slice(0,200)).catch(()=>'<unreadable>');
  console.log('snap content:', frameTxt.replace(/\n+/g,' '));
  await browser.close();
})().catch(e=>{console.error('FATAL',e.message);process.exit(1);});
