import vm from 'node:vm';
import fs from 'node:fs';
import assert from 'node:assert/strict';
import {webcrypto} from 'node:crypto';
const source=fs.readFileSync(new URL('../wordpress/wp-content/mu-plugins/rr-public-radio.js',import.meta.url),'utf8');
async function scenario(data,fail=false,noCard=false,offset=0,delay=0,changeVisibility=false){
 let mono=0; const timers=[],listeners={}; class Clock extends Date {static now(){return Date.now()+offset;}}
 const heading={},title={},next={dataset:{}},mini={};
 const cover={src:'https://www.radiorubben.no/logo.png',getAttribute(){return this.src;},removeAttribute(){},addEventListener(e,f){this.onerror=f;}};
 const player={querySelector(){return null;},prepend(img){this.img=img;}};
 const card={querySelector:s=>s==='.rr-radio-cover img'?cover:s==='h2'?heading:s.includes('h2')?heading:s.includes('div > p')?title:next};
 let options;const audio=null;
 const context={window:{RR_PUBLIC_RADIO:{logoUrl:'https://www.radiorubben.no/logo.png'},RR_ONE:{streamUrl:'https://5.249.252.78/radio.mp3'}},document:{hidden:false,getElementById:()=>audio,createElement:()=>({getAttribute(){return this.src;},removeAttribute(){},addEventListener(e,f){this.onerror=f;}}),querySelectorAll:s=>s==='.rr-mini-player'?[player]:s==='.rr-radio-card'?(noCard?[]:[card]):s==='.rr-play-toggle'?[{addEventListener:(event,handler)=>{click=handler;}}]:[mini],addEventListener(e,f){listeners[e]=f;}},crypto:webcrypto,performance:{now:()=>mono},Date:Clock,AbortController,setTimeout,clearTimeout,setInterval(f,ms){timers.push({f,ms});},fetch:async(url,o)=>{options=o;if(fail)throw Error();return{ok:true,json:async()=>{mono+=delay;if(changeVisibility){changeVisibility=false;listeners.visibilitychange();}return data?{...data,requestId:data.requestId??new URL(url).searchParams.get('requestId')}:null;}};}};
 vm.runInNewContext(source,context);await new Promise(resolve=>setImmediate(resolve));
 return{heading,title,next,mini,options,cover,player,timers,listeners,advance(ms){mono+=ms;}};
}
const data={schemaVersion:1,generatedAt:new Date().toISOString(),playing:true,now:{artist:'A <img onerror=bad>',title:'Song'},next:{artist:'B',title:'Next'}};
let r=await scenario(data);assert.equal(r.heading.textContent,'A <img onerror=bad>');assert.equal(r.next.textContent,'Om litt: B – Next');assert.equal(r.options.credentials,'omit');assert.ok(!('innerHTML' in r.heading));
r=await scenario({...data,generatedAt:'2000-01-01T00:00:00Z',requestId:'0'.repeat(32)});assert.equal(r.heading.textContent,'Radio Rubben');assert.ok(!r.next.textContent.includes('Next'));
r=await scenario(null,true);assert.equal(r.mini.textContent,'Radio Rubben');assert.ok(r.next.textContent.includes('ikke tilgjengelig'));
r=await scenario({...data,playing:false});assert.equal(r.heading.textContent,'Radio Rubben');
console.log('PASS public metadata: current/next, text-only rendering, no credentials, stale/offline/error clearing');
const artworkUrl='https://5.249.252.78/radio-status.php?cover='+'a'.repeat(64);
r=await scenario({...data,now:{...data.now,artworkUrl}});assert.equal(r.cover.src,artworkUrl);r.cover.onerror();assert.equal(r.cover.src,'https://www.radiorubben.no/logo.png');
for(const now of [{...data.now,artworkUrl:'https://evil.example/image.jpg'},{...data.now,artworkUrl,kind:'jingle'},data.now]){r=await scenario({...data,now});assert.equal(r.cover.src,'https://www.radiorubben.no/logo.png');}
r=await scenario({...data,now:{...data.now,artworkUrl},playing:false});assert.equal(r.cover.src,'https://www.radiorubben.no/logo.png');
console.log('PASS cover: matching public artwork, missing/jingle/offline/error logo, external URLs rejected');

r=await scenario({...data,now:{...data.now,artworkUrl}},false,true);assert.equal(r.player.img.src,artworkUrl);r.player.img.onerror();assert.equal(r.player.img.src,'https://www.radiorubben.no/logo.png');
r=await scenario(null,true,true);assert.equal(r.player.img.src,'https://www.radiorubben.no/logo.png');
console.log('PASS mobile cover without full card: artwork, error and offline fallback');

for(const offset of [31306,-31306,3600000,-3600000,86400000]){
 r=await scenario(data,false,false,offset);assert.equal(r.heading.textContent,data.now.artist,'clock offset '+offset);
}
r=await scenario(data);r.advance(30001);r.timers.find(t=>t.ms===1000).f();assert.equal(r.heading.textContent,'Radio Rubben','metadata expires independently of wall clock');
for(const invalid of [{...data,requestId:''},{...data,requestId:'old'},{...data,generatedAt:'invalid'},{...data,schemaVersion:2}]){
 r=await scenario(invalid);assert.equal(r.heading.textContent,'Radio Rubben','invalid reply rejected');
}
r=await scenario(data,false,false,0,6001);assert.equal(r.heading.textContent,'Radio Rubben','late reply rejected');
r=await scenario(data);r.listeners.visibilitychange();assert.equal(r.heading.textContent,'Radio Rubben','foreground discards old status');await new Promise(resolve=>setImmediate(resolve));assert.equal(r.heading.textContent,data.now.artist,'foreground gets fresh status');
console.log('PASS clock independence: reported +31.306s, +/-1h/day, replay, expiry, late reply and foreground recovery');


r=await scenario(data,false,false,0,0,true);assert.equal(r.heading.textContent,'Radio Rubben','in-flight reply from before a visibility change rejected');
