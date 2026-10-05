const {chromium}=require('playwright');
const fs=require('node:fs');
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.RR_BROWSER_CHANNEL?{channel:process.env.RR_BROWSER_CHANNEL}:{})});
 const report=[];fs.mkdirSync('theme-next/docs/page-preview',{recursive:true});
 for(const layout of ['standard','application','editorial','football'])for(const width of [320,390,768,1440]){
  const page=await browser.newPage({viewport:{width,height:900},reducedMotion:'reduce'}); const errors=[];
  page.on('pageerror',e=>errors.push(e.message));page.on('response',r=>{if(r.status()>=400)errors.push(r.status()+' '+r.url());});
  await page.goto('http://127.0.0.1:12380/page-preview-'+layout+'.html');
  await page.waitForFunction(()=>[...document.images].every(i=>i.complete&&i.naturalWidth));
  const state=await page.evaluate(()=>({overflow:document.documentElement.scrollWidth>innerWidth,main:document.querySelectorAll('main').length,h1:document.querySelectorAll('h1').length,gridColumns:document.querySelector('.rr-ds-grid')&&getComputedStyle(document.querySelector('.rr-ds-grid')).gridTemplateColumns.split(' ').length,links:[...document.querySelectorAll('.rr-section-nav a')].map(a=>({exists:!!document.querySelector(a.getAttribute('href')),height:a.getBoundingClientRect().height})),font:getComputedStyle(document.body).fontFamily}));
  if(state.overflow||state.main!==1||state.h1!==1||state.links.some(a=>!a.exists||a.height<44)||state.gridColumns!==(width<760?1:2)||!state.font.includes('Arial')||errors.length)throw Error(JSON.stringify({layout,width,state,errors}));
  if(layout!=='editorial'){
   const link=page.locator('.rr-section-nav a').last();await link.focus();await page.keyboard.press('Enter');
   const hash=new URL(page.url()).hash;if(!hash)throw Error('Keyboard anchor failed');
  }
  if(width===390||width===1440)await page.screenshot({path:`theme-next/docs/page-preview/${layout}-${width}.png`,fullPage:true});
  report.push({layout,width,...state,errors});await page.close();
 }
 await browser.close();fs.writeFileSync('theme-next/docs/page-preview/layout-check.json',JSON.stringify(report,null,2));console.log('PASS: '+report.length+' page layouts, widths, headings, anchor keyboard navigation, touch targets and profile font.');
})().catch(e=>{console.error(e);process.exit(1)});
