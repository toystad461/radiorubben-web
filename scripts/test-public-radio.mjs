import vm from 'node:vm';
import fs from 'node:fs';
import assert from 'node:assert/strict';
const source=fs.readFileSync(new URL('../wordpress/wp-content/mu-plugins/rr-public-radio.js',import.meta.url),'utf8');
async function scenario(data,fail=false){
 const heading={},title={},next={dataset:{}},mini={};
 const card={querySelector:s=>s==='h2'?heading:s.includes('h2')?heading:s.includes('div > p')?title:next};
 let options,click,loads=0;const audio={ended:false,load(){loads++;}};
 const context={window:{RR_ONE:{streamUrl:'https://5.249.252.78/radio.mp3'}},document:{hidden:false,getElementById:()=>audio,querySelectorAll:s=>s==='.rr-radio-card'?[card]:s==='.rr-play-toggle'?[{addEventListener:(event,handler)=>{click=handler;}}]:[mini],addEventListener(){}},Date,AbortController,setTimeout,clearTimeout,setInterval(){},fetch:async(url,o)=>{options=o;if(fail)throw Error();return{ok:true,json:async()=>data};}};
 vm.runInNewContext(source,context);await new Promise(resolve=>setImmediate(resolve));
 click();assert.equal(loads,0);audio.ended=true;click();assert.equal(loads,1,'ended stream reconnects instead of replaying old response');
 return{heading,title,next,mini,options};
}
const data={schemaVersion:1,generatedAt:new Date().toISOString(),playing:true,now:{artist:'A <img onerror=bad>',title:'Song'},next:{artist:'B',title:'Next'}};
let r=await scenario(data);assert.equal(r.heading.textContent,'A <img onerror=bad>');assert.equal(r.next.textContent,'Om litt: B – Next');assert.equal(r.options.credentials,'omit');assert.ok(!('innerHTML' in r.heading));
r=await scenario({...data,generatedAt:'2000-01-01T00:00:00Z'});assert.equal(r.heading.textContent,'Radio Rubben');assert.ok(!r.next.textContent.includes('Next'));
r=await scenario(null,true);assert.equal(r.mini.textContent,'Radio Rubben');assert.ok(r.next.textContent.includes('ikke tilgjengelig'));
r=await scenario({...data,playing:false});assert.equal(r.heading.textContent,'Radio Rubben');
console.log('PASS public metadata: current/next, text-only rendering, no credentials, stale/offline/error clearing');
