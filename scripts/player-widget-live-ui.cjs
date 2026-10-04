const {chromium}=require('playwright');
(async()=>{
  const browser=await chromium.launch({headless:true});
  for(const [path,selector,label] of [['/','.rrpw-home','home'],['/sport/','.rr-spillerkamper','sport']]) for(const width of [360,1280]){
    const page=await browser.newPage({viewport:{width,height:1200},deviceScaleFactor:1});
    const response=await page.goto('https://www.radiorubben.no'+path,{waitUntil:'domcontentloaded',timeout:45000});
    if(response.status()!==200)throw Error(path+' HTTP '+response.status());
    const headers=response.headers();
    if(!/no-cache|no-store/.test(headers['cache-control']||''))throw Error('Homepage lacks cache protection');
    const section=page.locator(selector);
    await section.evaluate(el=>window.scrollTo(0,window.scrollY+el.getBoundingClientRect().top-240));
    await page.waitForTimeout(1200);
    const first=section.locator('article.rrpw').first();
    if(!(await first.innerText()).includes('Brann 2'))throw Error('Today\'s match is missing');
    if(!/c849b4db/.test(await first.locator('.rrpw-watch').getAttribute('href')))throw Error('Wrong stream link');
    const overflow=await section.evaluate(el=>el.scrollWidth>el.clientWidth+1);
    if(overflow)throw Error('Widget overflows at '+width);
    const cardOverflow=await section.locator('article.rrpw').evaluateAll(els=>els.some(el=>el.scrollWidth>el.clientWidth+1));
    if(cardOverflow)throw Error('Card overflows at '+width+' on '+path);
    await page.screenshot({path:'player-widget-live-'+label+'-'+width+'.png'});
    console.log(JSON.stringify({path,width,headers:{cache:headers['cache-control'],litespeed:headers['x-litespeed-cache']},cards:await section.locator('article.rrpw').count(),text:await first.innerText()}));
    await page.close();
  }
  await browser.close();
})().catch(e=>{console.error(e);process.exit(1)});
