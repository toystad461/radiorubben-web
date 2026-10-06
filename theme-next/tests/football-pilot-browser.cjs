const {chromium}=require('playwright');
const fs=require('node:fs');
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'chrome'});
 const report=[];const out=process.env.RR_PILOT_OUTPUT||'/tmp/rr-football-pilot';fs.mkdirSync(out,{recursive:true});
 for(const width of [320,390,768,1440]){
  const page=await browser.newPage({viewport:{width,height:900},reducedMotion:'reduce'});const errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  await page.goto('http://127.0.0.1:8877/fotball-pilot/');
  await page.waitForSelector('#poll-live');
  await page.waitForFunction(()=>!document.querySelector('#poll-status').textContent.includes('Henter'));
  const state=await page.evaluate(()=>({overflow:document.documentElement.scrollWidth>innerWidth,main:document.querySelectorAll('main').length,h1:document.querySelectorAll('h1').length,polls:document.querySelectorAll('#poll-live').length,links:[...document.querySelectorAll('.rr-section-nav a')].map(a=>({exists:!!document.querySelector(a.getAttribute('href')),height:a.getBoundingClientRect().height})),unresolved:document.body.innerText.includes('[rr_')}));
  if(state.overflow||state.main!==1||state.h1!==1||state.polls!==1||state.unresolved||state.links.some(a=>!a.exists||a.height<44)||errors.length)throw Error(JSON.stringify({width,state,errors}));
  if(width===390)await page.screenshot({path:`${out}/football-mobile.png`});
  await page.locator('a[href="#poll-live"]').first().focus();await page.keyboard.press('Enter');
  if(!page.url().endsWith('#poll-live'))throw Error('Vote anchor failed');
  await page.screenshot({path:`${out}/football-${width}.png`,fullPage:true});
  report.push({width,...state,errors});await page.close();
 }
 await browser.close();fs.writeFileSync(`${out}/browser.json`,JSON.stringify(report,null,2));console.log('PASS: 4 real WordPress pilot widths, landmarks, single module, anchors and JS.');
})().catch(e=>{console.error(e);process.exit(1)});
