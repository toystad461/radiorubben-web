<?php
if (!defined('ABSPATH')) exit;
get_header();
?>
<style>
.rr-speaker{max-width:940px;margin:28px auto;padding:0 16px 110px;color:#f4f5f7;font-family:inherit}
.rr-speaker *{box-sizing:border-box}.rr-speaker h1{font-size:32px;line-height:1.2}.rr-speaker h2{font-size:22px}
.rr-speaker .badge{display:inline-block;background:#544019;color:#ffe5a0;padding:8px 12px;border-radius:8px;font-weight:700}
.rr-speaker .panel{background:#1b1e27;border:1px solid #505665;border-radius:16px;padding:20px;margin:16px 0}
.rr-speaker .scoreboard{text-align:center}.rr-speaker .score{font-size:52px;font-weight:800;margin:12px 0}.rr-speaker .clock{font-size:40px;font-variant-numeric:tabular-nums}
.rr-speaker .muted{color:#bcc3d0;font-size:14px;line-height:1.6}
.rr-speaker .row{display:flex;flex-wrap:wrap;gap:10px}.rr-speaker .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.rr-speaker button{border:1px solid #70788a;border-radius:9px;padding:13px 16px;min-height:48px;background:#292e3b;color:#fff;font-size:16px;cursor:pointer}
.rr-speaker button.primary{background:#bb293f;border-color:#e44359}.rr-speaker button:disabled{opacity:.4;cursor:default}
.rr-speaker button:focus-visible,.rr-speaker select:focus-visible,.rr-speaker input:focus-visible{outline:3px solid white;outline-offset:2px}
.rr-speaker label{display:block;font-size:14px;margin:12px 0}.rr-speaker input,.rr-speaker select,.rr-speaker textarea{width:100%;padding:12px;border:1px solid #70788a;border-radius:8px;background:#10131b;color:white;font:inherit;margin-top:6px;min-height:48px}
.rr-speaker .say{font-size:clamp(21px,5vw,29px);line-height:1.5;margin:0}
.rr-speaker ol{padding:0;list-style:none}.rr-speaker li{padding:14px 0;border-bottom:1px solid #454b58;line-height:1.5}
.rr-speaker textarea{min-height:200px;font-size:14px}
@media(max-width:500px){.rr-speaker .grid{grid-template-columns:1fr}.rr-speaker .panel{padding:16px}}
</style>
<main class="rr-speaker">
<span class="badge">FIKTIV TESTKAMP · Kun øving</span>
<h1>Speakerøving · fiktiv kamp</h1>
<section class="panel"><h2>Speakerboard · Dagens Bremnesing</h2><p>Styr avstemningen, registrer innbyttere og se resultatet i Speakerboardet. Der finner du også kampvalg og kampsponsor. Avstemningen stenger ved 75:00.</p><a style="color:#aaceff;text-decoration:underline" href="<?php echo esc_url(add_query_arg(['rr_vote'=>1,'rr_poll_control'=>1],remove_query_arg('rr_speaker_demo'))); ?>">Åpne Speakerboard →</a></section>
<p class="muted">Rubben Gul – Rubben Blå · Rubben treningsbane. Alle spillere og hendelser er fiktive. Ingen data sendes til ekte kampoversikter.</p>
<p class="muted" id="rr-storage">Testen lagres i denne nettleseren på denne enheten.</p>
<section class="panel scoreboard">
<strong>🟡 Rubben Gul &nbsp; – &nbsp; 🔵 Rubben Blå</strong>
<div class="score" id="rr-score">0 – 0</div>
<div id="rr-period">Ikke startet</div><div class="clock" id="rr-clock">00:00</div>
<p class="muted">Veiledende speakerklokke. Dommeren avgjør spilletid og tillegg.</p>
<div class="row">
<button class="primary" id="rr-start">Start 1. omgang</button>
<button id="rr-stop">Stopp klokken</button>
<button id="rr-half">Pause</button>
<button id="rr-second">Start 2. omgang</button>
<button id="rr-full">Avslutt kampen</button>
</div>
<details><summary style="margin-top:18px;cursor:pointer">Korriger kampklokken</summary>
<div class="grid"><label>Minutter<input id="rr-min" type="number" min="0" max="150" value="0"></label><label>Sekunder<input id="rr-sec" type="number" min="0" max="59" value="0"></label></div>
<button id="rr-setclock">Sett klokke</button></details>
</section>
<section class="panel">
<h2>Registrer hendelse</h2>
<div class="grid">
<label>Lag<select id="rr-team"><option value="home">🟡 Rubben Gul</option><option value="away">🔵 Rubben Blå</option></select></label>
<label>Spiller / spiller ut<select id="rr-player"></select></label>
</div>
<label>Spiller inn ved bytte<select id="rr-in"></select></label>
<div class="row"><button class="primary" data-event="goal">⚽ Mål</button><button data-event="yellow">🟨 Gult kort</button><button data-event="red">🟥 Rødt kort</button><button data-event="sub">🔁 Bytte</button></div>
<p class="muted">Velg lag og spiller først. Mål oppdaterer resultatet. Bytter registreres med spiller ut og spiller inn.</p>
<p id="rr-feedback" role="status"></p>
<button id="rr-undo">Angre siste handling</button>
</section>
<section class="panel"><h2>Les opp</h2><p class="say" id="rr-say" aria-live="polite"></p></section>
<section class="panel"><h2>Hendelser</h2><ol id="rr-events"></ol></section>
<details class="panel"><summary>Fiktive kamptropper</summary><div class="grid"><div><h2>Rubben Gul</h2><ul id="rr-home-roster"></ul></div><div><h2>Rubben Blå</h2><ul id="rr-away-roster"></ul></div></div></details>
<details class="panel"><summary>Rapport til gjennomlesing</summary><p class="muted">Rapporten bygger bare på det du registrerer i denne testen.</p><textarea readonly id="rr-report" aria-label="Fiktiv kamprapport"></textarea></details>
<button id="rr-reset">Nullstill testkamp</button>
</main>
<script>
(function(){
'use strict';
const key='rr-speaker-fiction-v1';
const $=id=>document.getElementById(id);
const clubs={home:'Rubben Gul',away:'Rubben Blå'};
const names={home:['Emil Nordvik','Jonas Bergli','Marius Solheim','Sander Vikdal','Andreas Fjell','Oskar Lundby','Tobias Strandvik','Martin Dalvik','Daniel Moen','Kristian Haugli','Filip Eikland','Adrian Bakkevik','Lukas Åsheim','Henrik Lunde'],
away:['Oliver Vestli','Noah Sundby','Elias Granvik','William Holmen','Aksel Markvik','Isak Sjøli','Magnus Tindal','Jakob Liheim','Theodor Myrvik','Leon Breidli','Sebastian Lauvik','Nikolai Varden','Benjamin Hovli','Mathias Engvik']};
const rosters={};
Object.keys(names).forEach(t=>rosters[t]=names[t].map((name,i)=>({no:i+1,name,bench:i>=11})));
const fresh=()=>({version:1,home:0,away:0,period:0,status:'Ikke startet',elapsed:0,running:false,started:0,events:[],say:'Velkommen til en fiktiv treningskamp mellom Rubben Gul og Rubben Blå!',history:[]});
let s=fresh();
try{const raw=JSON.parse(localStorage.getItem(key));if(raw && raw.version===1 && Array.isArray(raw.events) && Array.isArray(raw.history) && Number.isFinite(raw.elapsed) && Number.isFinite(raw.home) && Number.isFinite(raw.away))s=raw;}catch(e){}
function seconds(){return Math.max(0,s.elapsed+(s.running?Math.floor((Date.now()-s.started)/1000):0));}
function save(){try{localStorage.setItem(key,JSON.stringify(s));}catch(e){$('rr-storage').textContent='Lagring er ikke tilgjengelig. Testdata beholdes bare mens siden er åpen.';}}
function checkpoint(){const copy=JSON.parse(JSON.stringify(s));delete copy.history;s.history.push(copy);if(s.history.length>60)s.history.shift();}
function hold(){s.elapsed=seconds();s.running=false;s.started=0;}
function resume(){s.running=true;s.started=Date.now();}
function stamp(){const sec=seconds();const base=s.period===1?45:90;const minute=Math.floor(sec/60)+1;return minute>base?base+'+'+(minute-base):String(minute);}
function log(text){s.events.unshift({minute:stamp(),text});}
function commit(){save();render();}
function options(){
const list=rosters[$('rr-team').value];
for(const id of ['rr-player','rr-in']){
$(id).replaceChildren();
list.forEach(p=>{const o=document.createElement('option');o.value=p.no;o.textContent=p.no+'. '+p.name+(p.bench?' (reserve)':'');$(id).appendChild(o);});
}
$('rr-in').value='12';
}
function render(){
$('rr-score').textContent=s.home+' – '+s.away;$('rr-period').textContent=s.status;
$('rr-say').textContent=s.say;
$('rr-start').textContent=s.period===0?'Start 1. omgang':'Fortsett klokken';
$('rr-start').disabled=s.running || s.status==='Slutt' || s.status==='Pause';
$('rr-stop').disabled=!s.running;
$('rr-half').disabled=s.period!==1 || s.status==='Pause';
$('rr-second').disabled=s.status!=='Pause';
$('rr-full').disabled=s.period===0 || s.status==='Slutt';
$('rr-undo').disabled=!s.history.length;
document.querySelectorAll('[data-event]').forEach(b=>b.disabled=s.period===0 || s.status==='Slutt');
$('rr-events').replaceChildren();
if(!s.events.length){const li=document.createElement('li');li.textContent='Ingen hendelser registrert ennå.';$('rr-events').appendChild(li);}
s.events.forEach(e=>{const li=document.createElement('li');li.textContent=e.minute+'′ · '+e.text;$('rr-events').appendChild(li);});
$('rr-report').value='FIKTIV TESTKAMP – kun øving\nRubben Gul – Rubben Blå '+s.home+'–'+s.away+'\nStatus: '+s.status+'\n\n'+s.events.slice().reverse().map(e=>e.minute+'′ '+e.text).join('\n');
tick();
}
function tick(){const n=seconds();$('rr-clock').textContent=String(Math.floor(n/60)).padStart(2,'0')+':'+String(n%60).padStart(2,'0');}
$('rr-team').addEventListener('change',options);
$('rr-start').onclick=()=>{checkpoint();if(s.period===0){s.period=1;s.say='Da er vi i gang! God kamp til begge lag.';}s.status=s.period+'. omgang';resume();commit();};
$('rr-stop').onclick=()=>{checkpoint();hold();commit();};
$('rr-half').onclick=()=>{checkpoint();hold();log('Pause. Stillingen er '+s.home+'–'+s.away+'.');s.status='Pause';s.say='Det er pause. Rubben Gul '+s.home+', Rubben Blå '+s.away+'.';commit();};
$('rr-second').onclick=()=>{checkpoint();s.elapsed=2700;s.period=2;s.status='2. omgang';resume();log('Andre omgang startet.');s.say='Vi er klare for andre omgang. Stillingen er '+s.home+'–'+s.away+'.';commit();};
$('rr-full').onclick=()=>{if(!confirm('Avslutte den fiktive kampen?'))return;checkpoint();hold();log('Kampen er slutt. '+s.home+'–'+s.away+'.');s.status='Slutt';s.say='Kampen er slutt. Rubben Gul '+s.home+', Rubben Blå '+s.away+'. Takk til spillere, dommere og publikum!';commit();};
$('rr-setclock').onclick=()=>{const m=Number($('rr-min').value),sec=Number($('rr-sec').value);if(!Number.isInteger(m)||m<0||m>150||!Number.isInteger(sec)||sec<0||sec>59){$('rr-feedback').textContent='Bruk 0–150 minutter og 0–59 sekunder.';return;}checkpoint();s.elapsed=m*60+sec;s.started=s.running?Date.now():0;commit();};
document.querySelectorAll('[data-event]').forEach(b=>b.onclick=()=>{
const t=$('rr-team').value,p=rosters[t].find(x=>x.no===Number($('rr-player').value)),incoming=rosters[t].find(x=>x.no===Number($('rr-in').value)),type=b.dataset.event;
if(type==='sub' && p.no===incoming.no){$('rr-feedback').textContent='Velg forskjellige spillere inn og ut.';return;}
checkpoint();let text='';
if(type==='goal'){s[t]++;text='⚽ Mål til '+clubs[t]+': nr. '+p.no+' '+p.name+'. '+s.home+'–'+s.away+'.';s.say='Mål til '+clubs[t]+'! Målscorer er nummer '+p.no+', '+p.name+'. Stillingen er nå '+s.home+'–'+s.away+'.';}
if(type==='yellow'||type==='red'){const label=type==='yellow'?'Gult kort':'Rødt kort';text=(type==='yellow'?'🟨 ':'🟥 ')+label+' til '+clubs[t]+', nr. '+p.no+' '+p.name+'.';s.say=label+' til nummer '+p.no+', '+p.name+', '+clubs[t]+'.';}
if(type==='sub'){text='🔁 '+clubs[t]+': Ut nr. '+p.no+' '+p.name+'. Inn nr. '+incoming.no+' '+incoming.name+'.';s.say='Spillerbytte for '+clubs[t]+'. Ut går nummer '+p.no+', '+p.name+'. Inn kommer nummer '+incoming.no+', '+incoming.name+'.';}
log(text);$('rr-feedback').textContent='Hendelsen er registrert. Du kan angre siste handling.';commit();
});
$('rr-undo').onclick=()=>{if(!s.history.length)return;const old=s.history.pop(),history=s.history;s={...old,history};$('rr-feedback').textContent='Siste handling er angret.';commit();};
$('rr-reset').onclick=()=>{if(!confirm('Slette alle hendelser og starte testkampen på nytt?'))return;s=fresh();$('rr-feedback').textContent='Testkampen er nullstilt.';commit();};
for(const t of ['home','away'])rosters[t].forEach(p=>{const li=document.createElement('li');li.textContent=p.no+'. '+p.name+(p.bench?' (reserve)':'');$('rr-'+t+'-roster').appendChild(li);});
options();render();setInterval(tick,1000);
})();
</script>
<?php get_footer(); ?>
