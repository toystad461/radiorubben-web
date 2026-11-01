import vm from 'node:vm';
import fs from 'node:fs';
import assert from 'node:assert/strict';
const source = fs.readFileSync(new URL('../wordpress/wp-content/mu-plugins/rr-public-radio.js', import.meta.url), 'utf8');
let now = 0, id = 0, clicks, plays = 0, loads = 0, rejectPlay;
const timers = new Map(), events = {}, windowEvents = {};
const audio = {currentTime:0, error:null, ended:false, paused:true,
  addEventListener(e, f) {(events[e] ??= []).push(f);},
  load() {loads++; this.error = null; this.ended = false;},
  play() {plays++; this.paused = false; return rejectPlay ? Promise.reject(rejectPlay) : Promise.resolve();},
  pause() {this.paused = true; emit('pause');}
};
const button = {addEventListener(e,f) {clicks=f;}, setAttribute() {}};
function emit(e) {for(const f of events[e] ?? []) f();}
function click() {clicks({preventDefault(){},stopImmediatePropagation(){}});}
function timer(f, ms, interval=false) {const key=++id;timers.set(key,{f,at:now+ms,ms,interval});return key;}
async function advance(ms) {
 const end=now+ms;
 while(true) {
  const next=[...timers].filter(([,t])=>t.at<=end).sort((a,b)=>a[1].at-b[1].at)[0];
  if(!next)break;
  const [key,t]=next; now=t.at;timers.delete(key);if(t.interval)timers.set(key,{...t,at:now+t.ms});t.f();
  await new Promise(resolve=>setImmediate(resolve));
 }
 now=end;await new Promise(resolve=>setImmediate(resolve));
}
vm.runInNewContext(source, {
 window:{RR_ONE:{streamUrl:'https://5.249.252.78/radio.mp3'},addEventListener(e,f){windowEvents[e]=f;}},
 document:{hidden:true,body:{classList:{toggle(){}}},getElementById:()=>audio,querySelectorAll:s=>s==='.rr-play-toggle'?[button]:[],addEventListener(){}},
 Date:{now:()=>now},setTimeout:(f,ms)=>timer(f,ms),clearTimeout:key=>timers.delete(key),setInterval:(f,ms)=>timer(f,ms,true)
});
await advance(60000);assert.equal(plays,0,'never autostart');
click();assert.equal(plays,1);emit('playing');
audio.ended=true;emit('ended');await advance(3000);assert.equal(plays,2,'EOF retries without a click');assert.equal(loads,2);
audio.error={code:2};emit('error');await advance(5999);assert.equal(plays,2);await advance(1);assert.equal(plays,3,'failure backs off');
emit('playing');audio.currentTime=1;emit('timeupdate');
await advance(25000);await advance(3000);assert.equal(plays,4,'silent stalled connection recovers');
click();const stopped=plays;await advance(120000);assert.equal(plays,stopped,'Pause cancels timeout and retry');
emit('playing');assert.equal(audio.paused,true,'late playing event after Pause is stopped');
click();emit('playing');audio.error={code:2};emit('error');click();await advance(60000);assert.equal(plays,stopped+1,'Pause while retrying stays paused');
click();emit('playing');audio.error={code:2};emit('error');windowEvents.online();assert.equal(plays,stopped+3,'online retries promptly');
emit('playing');audio.error=null;audio.pause();await advance(60000);assert.equal(plays,stopped+3,'native pause is respected');
rejectPlay={name:'NotAllowedError'};click();await new Promise(resolve=>setImmediate(resolve));const blocked=plays;await advance(120000);assert.equal(plays,blocked,'browser autoplay block requires user action');
rejectPlay={name:'NetworkError'};click();await new Promise(resolve=>setImmediate(resolve));await advance(3000);assert.equal(plays,blocked+2,'rejected play retries');
click();await advance(60000);assert.equal(plays,blocked+2);
console.log('PASS reconnect: explicit Play, EOF, errors, backoff, stall, Pause, late events, online, autoplay policy');
