const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const path=require('node:path');
const fs=require('node:fs');
(async()=>{
 const browser=await chromium.launch({headless:true});const page=await browser.newPage();const errors=[];
 page.on('pageerror',e=>errors.push(e.message));
 await page.route(/^https?:/,r=>{const id=r.request().url().match(/fiks-no(3302|781)\.png$/)?.[1];return id?r.fulfill({contentType:'image/png',body:fs.readFileSync('wordpress/wp-content/plugins/radio-rubben-player-widget/tests/fixtures/logo-'+id+'.png')}):r.abort();});
 for(const width of [320,360,768,1280]){
  await page.setViewportSize({width,height:700});await page.goto('file://'+path.resolve('wordpress/wp-content/plugins/radio-rubben-player-widget/tests/compact-preview.html'));
  assert.equal(await page.locator('.rrpw-mini').count(),5);
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  assert.ok((await page.locator('.rrpw-compact').boundingBox()).height<410,'Compact height');
  assert.ok(await page.locator('.rrpw-mini').evaluateAll(els=>els.every(el=>el.scrollWidth<=el.clientWidth+1)),'No card overflow');
  const strip=page.locator('.rrpw-strip');if(width<768){await strip.evaluate(el=>el.scrollLeft=el.scrollWidth);assert.ok(await strip.evaluate(el=>el.scrollLeft>0),'Mobile swiping');await strip.evaluate(el=>el.scrollLeft=0);}
  assert.match(await page.locator('.rrpw-mygame').first().getAttribute('href'),/kampoversikt\.mygame\.no\/match\/fiks-no/);
  if(width===360||width===1280)await page.screenshot({path:'player-widget-compact-'+width+'.png'});
  await page.locator('.rrpw-compact>summary').click();assert.equal(await page.locator('.rrpw-strip').isVisible(),false);await page.locator('.rrpw-compact>summary').click();
 }
 const first=page.locator('.rrpw-mini[data-player-id="1011"]');
 await first.locator('[data-expires]').evaluateAll(els=>els.forEach(el=>el.dataset.expires='1'));
 await first.locator('[data-lineup-expires]').evaluate(el=>el.dataset.lineupExpires='1');await page.evaluate(()=>dispatchEvent(new Event('pageshow')));
 assert.equal(await first.locator('.rrpw-mygame,.rrpw-mini-watch').count(),0);assert.equal(await first.locator('.is-confirmed').count(),0);assert.equal(await first.locator('.rrpw-mini-status').isVisible(),false);
 await first.evaluate(el=>el.dataset.cardExpires='1');await page.evaluate(()=>dispatchEvent(new Event('pageshow')));assert.equal(await first.locator('a').count(),0);assert.ok(await first.isVisible(),'Player remains after fixture expiry');
 assert.ok(!/Tropp uavklart|Ingen kommende kamp|Kampdata uavklart/.test(await page.locator('.rrpw-compact').innerText()));
 assert.deepEqual(errors,[]);await browser.close();console.log('Compact layout, horizontal scroll, collapse and source expiry passed at four widths');
})().catch(e=>{console.error(e);process.exit(1)});
