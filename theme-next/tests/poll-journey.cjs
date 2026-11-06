const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync(__dirname+'/../rr-site-functions/inc/bremnes-poll-test.php','utf8');
function between(a,b){return source.slice(source.indexOf(a),source.indexOf(b,source.indexOf(a))).replace(/<\?php[\s\S]*?\?>/g,'{}');}
const functions=[between('function syncClock(', 'function clockElapsed('),between('function clockElapsed(', 'async function request('),between('async function request(', 'function updatePlayers('),between('function render(){','async function refresh('),between('async function refresh(', 'async function command('),between('async function command(',"if(el('poll-form'))")].join('\n');
const base=()=>({ok:true,match_id:99999991,session:'test',token:'test-token',opened:true,closed:false,period:1,running:false,elapsed:60,started:0,server_now_ms:1000,authenticated:true,login_nonce:'synthetic-login-nonce',logout_nonce:'synthetic-logout-nonce',eligible:true,roster_ready:true,voted:false,score:{home:0,away:0},candidates:{7:'TEST Spiller'},entered:{}});
function harness(fetch){
 const nodes={};
 for(const id of ['poll-status','poll-feedback','poll-player','poll-submit','poll-form','poll-login','poll-recover','poll-score','poll-clock-label','poll-clock','poll-clock-note'])nodes[id]={textContent:'',value:'7',hidden:false,disabled:false,dataset:{},parentElement:{classList:{toggle(){}}},replaceChildren(){},add(){},querySelector(){return null;}};
 const timers=new Map();let serial=0;
 const ctx=vm.createContext({fetch,location:{protocol:'https:',host:'example.test'},AbortController,URLSearchParams,URL,Date,Math,Number,Error,SyntaxError,Object,performance:{now:()=>1},setTimeout:(cb)=>{timers.set(++serial,cb);return serial;},clearTimeout:id=>timers.delete(id),Option:function(){},document:{querySelector:()=>null,querySelectorAll:()=>[]},nodes});
 vm.runInContext(`const endpoint='https://example.test/dagenskamp/?rr_match=99999991',expectedMatch=99999991,isControl=false,initialEligible=true,kickoff=0;const el=id=>nodes[id]||null;const pad=n=>String(n).padStart(2,'0');let state=null,busy=false,healthy=false,voteConfirmed=false,votePending=false,loginBusy=false,refreshController=null,clockAnchor=null,countdownAnchor=null,refreshSerial=0,refreshPending=false,candidateSignature='';function updateSpeaker(){}function updateMatchEvents(){}function updateRecentEvents(){}function updateLiveStatus(){}\n${functions}\nthis.api={request,refresh,command,render,set:s=>{state=s;healthy=true;},lockLogin:()=>{loginBusy=true;},get:()=>({state,busy,healthy,voteConfirmed,votePending,refreshPending})};`,ctx);
 return {api:ctx.api,nodes,expire:()=>{for(const cb of [...timers.values()])cb();},timers};
}
const response=data=>({ok:true,json:async()=>data});
const hanging=(_url,opts)=>new Promise((_resolve,reject)=>opts.signal.addEventListener('abort',()=>{const error=new Error('aborted');error.name='AbortError';reject(error);}));
test('GET timeout releases pending request and provides a safe retry',async()=>{const h=harness(hanging);const p=h.api.refresh();h.expire();await p;assert.equal(h.api.get().refreshPending,false);assert.equal(h.api.get().healthy,false);assert.equal(h.nodes['poll-recover'].hidden,false);});
test('wrong match response is rejected',async()=>{const h=harness(async()=>response({...base(),match_id:99999992}));await assert.rejects(h.api.request(),/Kampen kunne ikke/);});
test('non-JSON response has a readable recovery message',async()=>{const h=harness(async()=>({ok:true,json:async()=>{throw new SyntaxError('html');}}));await assert.rejects(h.api.request(),/Kunne ikke lese/);});
test('confirmed POST receipt survives failed following GET and closed poll',async()=>{const h=harness(async(_u,o)=>{if(o.method==='POST')return response({ok:true,voted:true,message:'Takk!'});throw Error('offline');});h.api.set({...base(),closed:true});await h.api.command({action:'vote'});assert.equal(h.api.get().busy,false);assert.equal(h.api.get().voteConfirmed,true);assert.match(h.nodes['poll-status'].textContent,/registrert/);assert.equal(h.nodes['poll-form'].hidden,true);assert.equal(h.nodes['poll-feedback'].textContent,'Takk!');});
test('double tap issues one POST; lost POST is resolved through GET',async()=>{let resolve,posts=0;const h=harness(async(_u,o)=>{if(o.method==='POST'){posts++;return new Promise(r=>{resolve=r;});}return response({...base(),voted:true});});h.api.set(base());const first=h.api.command({action:'vote'});assert.equal(await h.api.command({action:'vote'}),false);resolve(response({ok:true,voted:true}));await first;assert.equal(posts,1);assert.equal(h.api.get().voteConfirmed,true);});
test('lost reply with failed status never automatically posts again',async()=>{let posts=0;const h=harness(async(_u,o)=>{if(o.method==='POST')posts++;throw Error('offline');});h.api.set(base());await h.api.command({action:'vote'});assert.equal(h.api.get().busy,false);assert.equal(h.api.get().votePending,true);assert.equal(h.nodes['poll-submit'].disabled,true);assert.equal(posts,1);assert.equal(h.nodes['poll-recover'].hidden,false);});
test('lost POST response is confirmed by server status',async()=>{const h=harness(async(_u,o)=>{if(o.method==='POST')throw Error('offline');return response({...base(),voted:true});});h.api.set(base());await h.api.command({action:'vote'});assert.equal(h.api.get().voteConfirmed,true);assert.match(h.nodes['poll-status'].textContent,/registrert/);});
test('before opening and after closing hide login and ballot',()=>{const h=harness();for(const extra of [{opened:false},{closed:true}]){h.api.set({...base(),eligible:false,...extra});h.api.render();assert.equal(h.nodes['poll-form'].hidden,true);assert.equal(h.nodes['poll-login'].hidden,true);}});
test('expired login has a recovery path even when login form was not rendered',()=>{const h=harness();delete h.nodes['poll-login'];h.api.set({...base(),eligible:false,token:''});h.api.render();assert.equal(h.nodes['poll-recover'].hidden,false);assert.equal(h.nodes['poll-submit'].disabled,true);});
test('already logged in goes directly to player choice',()=>{const h=harness();h.api.set(base());h.api.render();assert.equal(h.nodes['poll-login'].hidden,true);assert.equal(h.nodes['poll-form'].hidden,false);assert.equal(h.nodes['poll-submit'].disabled,false);});
test('new voting session clears confirmation and player choice',async()=>{let next={...base(),voted:true};const h=harness(async()=>response(next));await h.api.refresh();next={...base(),session:'new',token:'new-token'};await h.api.refresh();assert.equal(h.api.get().voteConfirmed,false);assert.equal(h.nodes['poll-player'].value,'');assert.equal(h.nodes['poll-feedback'].textContent,'');});
test('another login on same match does not inherit previous receipt',async()=>{let next={...base(),voted:true};const h=harness(async()=>response(next));await h.api.refresh();next={...base(),token:'other-session'};await h.api.refresh();assert.equal(h.api.get().voteConfirmed,false);});

test('login nonce is refreshed and app handoff stays disabled',()=>{
 const h=harness(),input={},button={};
 h.nodes['poll-login'].querySelector=selector=>selector.startsWith('[name=')?input:button;
 h.api.set({...base(),authenticated:false,eligible:false});h.api.render();
 assert.equal(input.value,'synthetic-login-nonce');assert.equal(button.disabled,false);
 h.api.lockLogin();h.api.render();assert.equal(button.disabled,true);
});
