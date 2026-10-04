const {chromium}=require('playwright');const assert=require('node:assert/strict');
(async()=>{
 const browser=await chromium.launch({headless:true});
 for(const route of ['/','/sport/'])for(const width of [360,1280]){
  const page=await browser.newPage({viewport:{width,height:850}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
  const response=await page.goto('https://www.radiorubben.no'+route,{waitUntil:'domcontentloaded',timeout:45000});
  assert.equal(response.status(),200);assert.match(response.headers()['cache-control'],/no-cache|no-store/);
  const widget=page.locator('.rrpw-compact');assert.equal(await widget.locator('.rrpw-mini').count(),12);
  await widget.evaluate(el=>window.scrollTo(0,window.scrollY+el.getBoundingClientRect().top-220));await page.waitForTimeout(800);
  assert.ok((await widget.boundingBox()).height<430,'Compact height under active theme');
  assert.ok(await widget.locator('.rrpw-mini').evaluateAll(els=>els.every(el=>el.scrollWidth<=el.clientWidth+1)),'No tile overflow');
  assert.ok(!(await widget.innerText()).includes('Spiller nå'),'No clock-inferred live participation');
  assert.ok(!/Tropp uavklart|Ingen kommende kamp|Ingen kamp de neste|Kampdata uavklart/.test(await widget.innerText()));
  const times=await widget.locator('.rrpw-mini').evaluateAll(els=>els.map(el=>Number(el.dataset.kickoff)||Number.MAX_SAFE_INTEGER));
  assert.deepEqual(times,[...times].sort((a,b)=>a-b),'Nearest fixture first');
  assert.deepEqual((await widget.locator('.rrpw-mini').evaluateAll(els=>els.map(el=>Number(el.dataset.playerId)))).sort((a,b)=>a-b),[1011,1012,1013,1014,1037,1081,1082,1083,1084,1085,1086,1087]);
  const links=await widget.locator('.rrpw-mygame').evaluateAll(els=>els.map(a=>a.href));
  assert.ok(links.length>0,'Verified MyGame page link rendered');assert.ok(links.every(url=>/^https:\/\/kampoversikt\.mygame\.no\/match\/fiks-no[0-9]+$/.test(url)));
  if(width===360){const strip=widget.locator('.rrpw-strip');await strip.evaluate(el=>el.scrollLeft=el.scrollWidth);assert.ok(await strip.evaluate(el=>el.scrollLeft>0));await strip.evaluate(el=>el.scrollLeft=0);}
  await page.screenshot({path:'player-widget-compact-live-'+(route==='/'?'home':'sport')+'-'+width+'.png'});
  await widget.locator(':scope>summary').click();assert.equal(await widget.locator('.rrpw-strip').isVisible(),false);
  console.log(JSON.stringify({route,width,height:(await widget.boundingBox()).height,mygame:links,errors}));await page.close();
 }
 await browser.close();
})().catch(e=>{console.error(e);process.exit(1)});
