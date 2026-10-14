const {execFileSync}=require('node:child_process'),path=require('node:path'),fs=require('node:fs'),assert=require('node:assert/strict');
const {chromium}=require('playwright');
(async()=>{const html=execFileSync('php',[path.join(__dirname,'visual.php')],{encoding:'utf8'});const browser=await chromium.launch();try{
 for(const width of [375,390,1280]){const page=await browser.newPage({viewport:{width,height:900}});await page.route('http**/*',r=>r.abort());await page.setContent(html);
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,'no horizontal overflow');
 for(const box of await page.locator('[data-viewport]').all()){
  const label=box.locator('.rr-ai-image-label');assert.equal(await label.innerText(),'AI-generert illustrasjon');
  const b=await box.boundingBox(),l=await label.boundingBox();assert(l.y>=b.y-1&&l.y+l.height<=b.y+b.height+1&&l.x>=b.x-1&&l.x+l.width<=b.x+b.width+1,'label remains within cropped image container');
 }
 if(process.env.MEDIA_SCREENSHOT_DIR){fs.mkdirSync(process.env.MEDIA_SCREENSHOT_DIR,{recursive:true});await page.screenshot({path:path.join(process.env.MEDIA_SCREENSHOT_DIR,`image-policy-${width}.png`),fullPage:true});}
 await page.close();console.log(`PASS visual image labels at ${width}px`);
 }
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1)});
