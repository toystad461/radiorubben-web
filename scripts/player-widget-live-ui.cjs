const {chromium}=require('playwright');
(async()=>{
  const browser=await chromium.launch({headless:true});
  for(const width of [360,1280]){
    const page=await browser.newPage({viewport:{width,height:1000},deviceScaleFactor:1});
    const response=await page.goto('https://www.radiorubben.no/',{waitUntil:'domcontentloaded',timeout:45000});
    if(response.status()!==200)throw Error('Homepage HTTP '+response.status());
    const headers=response.headers();
    if(!/no-cache|no-store/.test(headers['cache-control']||''))throw Error('Homepage lacks cache protection');
    const section=page.locator('.rrpw-home');
    await section.scrollIntoViewIfNeeded();
    await page.waitForTimeout(1200);
    const first=section.locator('article.rrpw').first();
    if(!(await first.innerText()).includes('Brann 2'))throw Error('Today\'s match is missing');
    if(!/c849b4db/.test(await first.locator('.rrpw-watch').getAttribute('href')))throw Error('Wrong stream link');
    const overflow=await section.evaluate(el=>el.scrollWidth>el.clientWidth+1);
    if(overflow)throw Error('Widget overflows at '+width);
    await section.screenshot({path:'player-widget-live-'+width+'.png'});
    console.log(JSON.stringify({width,headers:{cache:headers['cache-control'],litespeed:headers['x-litespeed-cache']},cards:await section.locator('article.rrpw').count(),text:await first.innerText()}));
    await page.close();
  }
  await browser.close();
})().catch(e=>{console.error(e);process.exit(1)});
