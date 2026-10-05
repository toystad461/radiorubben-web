const {chromium}=require('playwright');
const fs=require('node:fs');
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.RR_BROWSER_CHANNEL ? {channel:process.env.RR_BROWSER_CHANNEL} : {})});
 const report=[];fs.mkdirSync('theme-next/docs/home-preview',{recursive:true});
 for(const scenario of ['radio','news','sport'])for(const width of [320,390,768,1440]){
  const page=await browser.newPage({viewport:{width,height:900},reducedMotion:'reduce'});const errors=[];const failed=[];
  page.on('pageerror',e=>errors.push(e.message));page.on('response',r=>{if(r.status()>=400)failed.push({url:r.url(),status:r.status()});});
  await page.goto('http://127.0.0.1:12379/preview-'+scenario+'.html');await page.waitForLoadState('networkidle');
  await page.evaluate(()=>document.querySelectorAll('img[loading="lazy"]').forEach(i=>i.loading='eager'));
  await page.waitForFunction(()=>[...document.images].every(i=>i.complete&&i.naturalWidth>0));
  const state=await page.evaluate(()=>({width:innerWidth,scrollWidth:document.documentElement.scrollWidth,focus:document.querySelector('.rr-universes').dataset.focus,sections:[...document.querySelectorAll('.rr-universes>section')].map(e=>e.id),audioCount:document.querySelectorAll('audio').length,images:[...document.images].map(i=>({src:i.src,loaded:i.complete&&i.naturalWidth>0})),mini:!!document.querySelector('.rr-mini-player')}));
  if(width===390||width===1440)await page.screenshot({path:'theme-next/docs/home-preview/'+scenario+'-'+width+'.png',fullPage:true});
  const expected={radio:'nettradio',news:'nyheter',sport:'sport'}[scenario];
  if(state.focus!==scenario||state.sections[0]!==expected||state.scrollWidth>width||state.audioCount!==1||state.images.some(i=>!i.loaded)||errors.length||failed.length)throw Error('Layout/fixture failure: '+JSON.stringify({scenario,width,state,errors,failed}));
  if(width<=1100){await page.locator('.rr-menu-toggle').click();if(await page.locator('.rr-menu-toggle').getAttribute('aria-expanded')!=='true')throw Error('Menu did not open');await page.keyboard.press('Escape');if(await page.locator('.rr-menu-toggle').getAttribute('aria-expanded')!=='false')throw Error('Menu did not close');}
  await page.locator('.rr-u-play').click();await page.waitForFunction(()=>[...document.querySelectorAll('.rr-player-message')].every(e=>e.textContent==='Kunne ikke starte lyden. Prøv igjen.'));
  if(await page.locator('.rr-play-toggle:disabled').count())throw Error('Controls stayed locked after rejected play');
  report.push({scenario,width,state,errors,failed,menuAndPlayerRecovery:true});await page.close();
 }
 fs.writeFileSync('theme-next/docs/home-preview/layout-check.json',JSON.stringify(report,null,2));
 console.log(JSON.stringify(report.map(r=>({scenario:r.scenario,width:r.width,overflow:r.state.scrollWidth-r.width,focus:r.state.focus,errors:r.errors,failed:r.failed,brokenImages:r.state.images.filter(i=>!i.loaded).length,audioCount:r.state.audioCount})),null,2));
 await browser.close();
})().catch(e=>{console.error(e);process.exit(1)});
