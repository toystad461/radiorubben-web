const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const path = require('node:path');
const fs = require('node:fs');
(async () => {
  const browser = await chromium.launch({headless:true});
  const page = await browser.newPage();
  // Fixtures for the two observed logos; no network and no video playback.
  let failImages=false;
  await page.route(/^https?:/, route => {
    const id=route.request().url().match(/fiks-no(3302|781)\.png$/)?.[1];
    if (id && !failImages) return route.fulfill({contentType:'image/png',body:fs.readFileSync(`wordpress/wp-content/plugins/radio-rubben-player-widget/tests/fixtures/logo-${id}.png`)});
    return route.abort();
  });
  const errors=[]; page.on('pageerror', error=>errors.push(error.message));
  const file=path.resolve('wordpress/wp-content/plugins/radio-rubben-player-widget/tests/preview.html');
  for (const width of [320,360,736,1100]) {
    await page.setViewportSize({width,height:1000});
    await page.goto('file://'+file);
    await page.locator('.rrpw-watch').waitFor();
    assert.match(await page.locator('.rrpw-watch').getAttribute('href'), /^https:\/\/play\.tv2\.no\/gpid\//);
    assert.equal(await page.locator('.rrpw-player-state').textContent(),'Tropp ikke bekreftet');
    const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth);
    assert.equal(overflow,false,`${width}px overflow`);
    assert.ok(await page.locator('.rrpw-crest img').evaluateAll(images=>images.every(img=>img.complete && img.naturalWidth>0)),'Club crests loaded');
    await page.locator('summary').click();
    assert.equal(await page.locator('details').getAttribute('open'),'');
    await page.locator('summary').click();
    if (width===360 || width===736) await page.locator('.rrpw').screenshot({path:`player-widget-${width}.png`});
  }
  failImages=true;await page.reload();
  assert.ok(await page.locator('.rrpw-crest img').evaluateAll(images=>images.every(img=>img.hidden)),'Broken crest fallback');
  await page.locator('.rrpw-watch').evaluate(a=>a.dataset.expires='1');
  await page.evaluate(()=>window.dispatchEvent(new Event('pageshow')));
  assert.equal(await page.locator('.rrpw-watch').count(),0,'Expired cache must not keep CTA');
  await page.locator('.rrpw').evaluate(a=>a.dataset.cardExpires='1');
  await page.evaluate(()=>window.dispatchEvent(new Event('pageshow')));
  assert.equal(await page.locator('.rrpw').count(),0,'Expired schedule hidden');
  assert.deepEqual(errors,[]);
  await browser.close(); console.log('Player widget layout and stale-cache checks passed at 320, 360, 736 and 1100 px');
})().catch(e=>{console.error(e);process.exit(1)});
