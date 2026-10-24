// Rendered PHP fixtures only. No live site login, real approvals, network facts or AI calls.
const {chromium}=require(process.env.RRFR_PLAYWRIGHT);
const fs=require('fs');
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.CHROME_PATH});
 try {
  for(const width of [320,390,1280]) {
   const page=await browser.newPage({viewport:{width,height:844}});
   for(const name of ['ready','blocked','queue',...(fs.existsSync(process.env.RRFR_RENDER_DIR+'/followup.html')?['followup']:[]),...(fs.existsSync(process.env.RRFR_RENDER_DIR+'/weekly.html')?['weekly']:[])]) {
    await page.goto('file://'+process.env.RRFR_RENDER_DIR+'/'+name+'.html');
    const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth);
    if(overflow)throw new Error(name+' overflows at '+width);
    if(!['queue','followup','weekly'].includes(name)&&!(await page.locator('.rrfr-review-prose').innerText()).trim())throw new Error('Article text missing');
    if(name==='followup'&&!(await page.locator('.rrfr-followup-list').innerText()).includes('Sluttstatus'))throw new Error('Match progress missing');
    if(name==='weekly') {
     const layouts=await page.locator('.rrfr-match-layout').all();
     for(const layout of layouts){
      const boxes=await layout.evaluate(el=>[el.children[0],el.children[1],el.children[2]].map(x=>{const r=x.getBoundingClientRect();return {x:r.x,right:r.right,width:r.width}}));
      if(!(boxes[0].right<=boxes[1].x&&boxes[1].right<=boxes[2].x))throw new Error('Score not between teams at '+width);
     }
     if(await page.locator('.rrfr-match-story-link').count()!==1)throw new Error('Public story link missing');
     await page.screenshot({path:process.env.RRFR_RENDER_DIR+'/weekly-'+width+'.png',fullPage:true});
    }
    if(name==='followup') {
     if(await page.locator('table').count())throw new Error('Legacy white table remains');
     await page.locator('summary').first().click();
     if(!(await page.locator('dl').first().isVisible()))throw new Error('Technical details inaccessible');
     await page.locator('summary').first().click();
     await page.screenshot({path:process.env.RRFR_RENDER_DIR+'/followup-'+width+'.png',fullPage:true});
    }
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
