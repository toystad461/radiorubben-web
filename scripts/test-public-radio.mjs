import vm from 'node:vm';
import fs from 'node:fs';
import assert from 'node:assert/strict';
const source=fs.readFileSync(new URL('../wordpress/wp-content/mu-plugins/rr-public-radio.js',import.meta.url),'utf8');
async function scenario(data,fail=false){
 const heading={},title={},next={dataset:{}},mini={};
 const cover={src:'https://www.radiorubben.no/logo.png',getAttribute(){return this.src;},removeAttribute(){},addEventListener(e,f){this.onerror=f;}};
 const card={querySelector:s=>s==='.rr-radio-cover img'?cover:s==='h2'?heading:s.includes('h2')?heading:s.includes('div > p')?title:next};
 let options;const audio=null;
 const context={window:{RR_ONE:{streamUrl:'https://5.249.252.78/radio.mp3'}},document:{hidden:false,getElementById:()=>audio,querySelectorAll:s=>s==='.rr-radio-card'?[card]:s==='.rr-play-toggle'?[{addEventListener:(event,handler)=>{click=handler;}}]:[mini],addEventListener(){}},Date,AbortController,setTimeout,clearTimeout,setInterval(){},fetch:async(url,o)=>{options=o;if(fail)throw Error();return{ok:true,json:async()=>data};}};
 vm.runInNewContext(source,context);await new Promise(resolve=>setImmediate(resolve));
 return{heading,title,next,mini,options,cover};
}
const data={schemaVersion:1,generatedAt:new Date().toISOString(),playing:true,now:{artist:'A <img onerror=bad>',title:'Song'},next:{artist:'B',title:'Next'}};
let r=await scenario(data);assert.equal(r.heading.textContent,'A <img onerror=bad>');assert.equal(r.next.textContent,'Om litt: B – Next');assert.equal(r.options.credentials,'omit');assert.ok(!('innerHTML' in r.heading));
r=await scenario({...data,generatedAt:'2000-01-01T00:00:00Z'});assert.equal(r.heading.textContent,'Radio Rubben');assert.ok(!r.next.textContent.includes('Next'));
r=await scenario(null,true);assert.equal(r.mini.textContent,'Radio Rubben');assert.ok(r.next.textContent.includes('ikke tilgjengelig'));
r=await scenario({...data,playing:false});assert.equal(r.heading.textContent,'Radio Rubben');
console.log('PASS public metadata: current/next, text-only rendering, no credentials, stale/offline/error clearing');
const artworkUrl='https://5.249.252.78/radio-status.php?cover='+'a'.repeat(64);
r=await scenario({...data,now:{...data.now,artworkUrl}});assert.equal(r.cover.src,artworkUrl);r.cover.onerror();assert.equal(r.cover.src,'https://www.radiorubben.no/logo.png');
for(const now of [{...data.now,artworkUrl:'https://evil.example/image.jpg'},{...data.now,artworkUrl,kind:'jingle'},data.now]){r=await scenario({...data,now});assert.equal(r.cover.src,'https://www.radiorubben.no/logo.png');}
r=await scenario({...data,now:{...data.now,artworkUrl},playing:false});assert.equal(r.cover.src,'https://www.radiorubben.no/logo.png');
console.log('PASS cover: matching public artwork, missing/jingle/offline/error logo, external URLs rejected');
