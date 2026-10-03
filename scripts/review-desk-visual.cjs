// Rendered PHP fixtures only. No live site login, real approvals, network facts or AI calls.
const {chromium}=require(process.env.RRFR_PLAYWRIGHT);
const fs=require('fs');
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.CHROME_PATH});
 try {
  for(const width of [390,1280]) {
   const page=await browser.newPage({viewport:{width,height:844}});
   for(const name of ['ready','blocked','queue']) {
    await page.goto('file://'+process.env.RRFR_RENDER_DIR+'/'+name+'.html');
    const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth);
    if(overflow)throw new Error(name+' overflows at '+width);
    const approve=page.locator('button[value="approve"]');
    if(name==='ready'&&await approve.isDisabled())throw new Error('Ready approval disabled');
    if(name==='blocked'&&!(await approve.isDisabled()))throw new Error('Blocked approval enabled');
    if(name==='ready') {
     await page.locator('summary').filter({hasText:'Be om endringer'}).click();
     await page.locator('#rrfr-comment').fill('Behold kildelenkene.');
     await page.locator('summary').filter({hasText:'Be om endringer'}).click();
    }
    if(width===390) {
     const shot=await page.screenshot({type:'jpeg',quality:55,fullPage:true});
     console.log('RRFR_SCREENSHOT_'+name+'='+shot.toString('base64'));
    }
   }
   await page.close();
  }
  console.log('Mobile and desktop reading, overflow and approval states verified. No form submitted.');
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
