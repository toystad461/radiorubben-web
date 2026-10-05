const fs=require('node:fs');
const assert=require('node:assert/strict');
const {chromium}=require('playwright');
(async()=>{
 const root='wordpress/wp-content/plugins/radio-rubben-player-widget';
 let html=fs.readFileSync('widget-preview.html','utf8').replaceAll('https://example.test/wp-json/','http://widget.test/wp-json/');
 html=html.replace(/<link[^>]+>/g,'').replace(/<script[^>]*src=[^>]*><\/script>/g,'');
 html=html.replace('</body>','<style>'+fs.readFileSync(root+'/assets/widget.css','utf8')+'</style><script>'+fs.readFileSync(root+'/assets/widget.js','utf8')+'</script></body>');
 const browser=await chromium.launch({headless:true});const page=await browser.newPage({viewport:{width:360,height:800}});
 let polls=0;
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('http://widget.test/**',route=>{
  if(route.request().url().includes('/wp-json/')){polls++;return route.fulfill({contentType:'application/json',body:JSON.stringify({html:html.replace('På benken','Spiller nå'),updated_at:Date.now()/1000})});}
  return route.fulfill({contentType:'text/html',body:html});
 });
 await page.clock.install();await page.goto('http://widget.test/');
 assert.equal(await page.locator('.rrpw-mini-status').first().textContent(),'På benken');
 await page.evaluate(()=>document.querySelector('.rrpw-strip').scrollLeft=180);
 const scroll=await page.evaluate(()=>document.querySelector('.rrpw-strip').scrollLeft);
 await page.clock.fastForward(61000);
 await page.waitForFunction(()=>document.querySelector('.rrpw-mini-status').textContent==='Spiller nå');
 assert.equal(polls,1);assert.equal(await page.locator('.rrpw-mini').count(),5);
 assert.equal(await page.evaluate(()=>document.querySelector('.rrpw-strip').scrollLeft),scroll);
 await page.locator('summary').click();await page.locator('summary').blur();
 await page.clock.fastForward(60000);
 await page.waitForFunction(()=>!document.querySelector('.rrpw-compact').open);
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
 await page.screenshot({path:'player-live-ui-360.png'});
 assert.deepEqual(errors,[]);await browser.close();
 console.log('Minute refresh, exact status update, scroll, collapse and mobile overflow verified');
})().catch(e=>{console.error(e);process.exit(1);});
