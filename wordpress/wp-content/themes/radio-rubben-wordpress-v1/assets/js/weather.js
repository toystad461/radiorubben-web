(() => {
'use strict';
const root=document.querySelector('.rr-weather');
if(!root)return;
const status=root.querySelector('.rr-weather-status'),result=root.querySelector('.rr-weather-result'),select=root.querySelector('select');
let chosen={place:'rubbestadneset',name:'Rubbestadneset'};
const valid=x=>x&&typeof x.name==='string'&&x.name.length<160&&(
 (typeof x.place==='string'&&[...select.options].some(o=>o.value===x.place&&x.place!=='custom'))||
 (typeof x.lat==='number'&&Number.isFinite(x.lat)&&x.lat>=57&&x.lat<=81&&typeof x.lon==='number'&&Number.isFinite(x.lon)&&x.lon>=3&&x.lon<=35));
let locationRequest=0; // A manual selection invalidates any pending position request.
function syncSelect(){const custom=select.querySelector('option[value="custom"]');custom.textContent=chosen.place?'Mitt valgte sted':chosen.name;select.value=chosen.place||'custom';}
syncSelect();
const nf=new Intl.NumberFormat('nb-NO',{maximumFractionDigits:1});
const fmt=(v,s='')=>typeof v==='number'&&Number.isFinite(v)?nf.format(v)+s:'–';
const time=t=>new Intl.DateTimeFormat('nb-NO',{timeZone:'Europe/Oslo',hour:'2-digit',minute:'2-digit'}).format(new Date(t));
const date=t=>new Intl.DateTimeFormat('nb-NO',{timeZone:'Europe/Oslo',weekday:'short',day:'numeric',month:'short'}).format(new Date(t));
const key=t=>new Intl.DateTimeFormat('sv-SE',{timeZone:'Europe/Oslo',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date(t));
const esc=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function weather(code) {
 const c=code||'';
 if(c.includes('thunder'))return 'Torden';
 if(c.includes('sleet'))return 'Sludd';
 if(c.includes('snow'))return 'Snø';
 if(c.includes('rain'))return c.includes('showers')?'Regnbyger':'Regn';
 if(c.includes('fog'))return 'Tåke';
 if(c.includes('partlycloudy'))return 'Delvis skyet';
 if(c.includes('fair'))return 'Lettskyet';
 if(c.includes('clearsky'))return 'Klarvær';
 if(c.includes('cloudy'))return 'Skyet';
 return 'Værvarsel';
}
function icon(code) {
 const c=code||'',night=c.includes('_night');
 const sun='<circle cx="16" cy="16" r="8" fill="#ffd477" stroke="#ffd477"/><path d="M16 2v3m0 22v3M2 16h3m22 0h3M6 6l2 2m16 16 2 2M6 26l2-2M24 8l2-2" stroke="#ffd477"/>';
 const moon='<path d="M23 5a12 12 0 1 0 8 19A13 13 0 0 1 23 5Z" fill="#d4dcff" stroke="#d4dcff"/>';
 const cloud='<path d="M12 32h24a8 8 0 0 0 0-16 11 11 0 0 0-21-1 8.5 8.5 0 0 0-3 17Z" fill="#b9cde5" stroke="#d7e6f7"/>';
 let p='';
 if(!c)return '<svg class="rr-weather-icon" viewBox="0 0 48 48" aria-hidden="true"><text x="24" y="34" text-anchor="middle" fill="#b9cde5" font-size="32">?</text></svg>';
 if(/clearsky/.test(c))p=night?moon:sun;
 else {if(/fair|partlycloudy|showers/.test(c))p=night?moon:sun;p+=cloud;
 if(/rain|sleet/.test(c))p+='<path d="m15 37-2 5m12-5-2 5m12-5-2 5" stroke="#74bdff"/>';
 if(/snow|sleet/.test(c))p+='<path d="M18 37v7m-3.5-3.5h7m12-3.5v7m-3.5-3.5h7" stroke="#edf5ff"/>';
 if(/thunder/.test(c))p+='<path d="m28 29-7 10h7l-5 8" stroke="#ffd477"/>';
 if(/fog/.test(c))p+='<path d="M9 38h30M14 44h20" stroke="#cad4df"/>';
 }
 return '<svg class="rr-weather-icon" viewBox="0 0 48 48" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'+p+'</svg>';
}

let controller,serial=0,last=0;
async function load() {
 const id=++serial;
 if(controller)controller.abort();
 controller=new AbortController();
 const timer=setTimeout(()=>controller.abort(),20000);
 status.textContent='Henter værvarselet …';
 result.hidden=true;
 try {
  const u=new URL(root.dataset.endpoint,location.href);u.searchParams.set('action','rr_weather');if(chosen.place)u.searchParams.set('place',chosen.place);else {u.searchParams.set('lat',chosen.lat.toFixed(3));u.searchParams.set('lon',chosen.lon.toFixed(3));}
  const r=await fetch(u,{signal:controller.signal,cache:'no-store',credentials:'same-origin'});
  const j=await r.json();
  if(!r.ok||!j.success)throw Error('unavailable');
  if(id!==serial)return;
  const d=j.data,now=Date.now();
  const hours=(d.hours||[]).filter(h=>Date.parse(h.time)>=now-3600000&&Number.isFinite(h.temp));
  if(!hours.length||now-Date.parse(d.updated)>24*3600000)throw Error('stale');
  const h=hours[0],next=hours.filter(x=>Date.parse(x.time)>=now).slice(0,12);
  const symbol=h.symbol||'';
  root.dataset.sky=/thunder/.test(symbol)?'storm':/rain|sleet/.test(symbol)?'rain':/snow/.test(symbol)?'snow':/clearsky|fair/.test(symbol)?'clear':'cloudy';
  root.dataset.night=String(symbol.includes('_night'));
  const days=new Map();
  for(const x of hours){const k=key(x.time);if(!days.has(k))days.set(k,[]);days.get(k).push(x);}
  const daily=[...days.values()].slice(0,5).map(a=>'<div class="rr-weather-day"><strong>'+esc(date(a[0].time))+'</strong><span>'+fmt(Math.round(Math.min(...a.map(x=>x.temp))),'°')+' / '+fmt(Math.round(Math.max(...a.map(x=>x.temp))),'°')+'</span></div>').join('');
  result.innerHTML='<div class="rr-weather-now">'+icon(h.symbol)+'<div><strong class="rr-weather-temp">'+fmt(Math.round(h.temp),'°')+'</strong><p>'+esc(weather(h.symbol))+'</p><small>Varsel kl. '+esc(time(h.time))+'</small></div><dl><div><dt>Vind</dt><dd>'+fmt(h.wind,' m/s')+'</dd></div><div><dt>Nedbør neste time</dt><dd>'+fmt(h.rain,' mm')+'</dd></div></dl></div><div class="rr-weather-hours" tabindex="0" role="region" aria-label="Timevarsel, bla for flere timer">'+next.map(x=>'<div class="rr-weather-hour"><span>'+esc(time(x.time))+'</span><span role="img" aria-label="'+esc(weather(x.symbol))+'">'+icon(x.symbol)+'</span><strong>'+fmt(Math.round(x.temp),'°')+'</strong><small>'+fmt(x.rain,' mm')+'</small></div>').join('')+'</div><details class="rr-weather-extended"><summary>De neste dagene</summary><div class="rr-weather-days">'+daily+'</div><p class="rr-weather-small">Laveste / høyeste temperatur i varselpunktene. Første dag viser resten av dagen.</p></details>';
  result.hidden=false;
  status.textContent='Oppdatert '+date(d.updated)+' kl. '+time(d.updated)+(now-Date.parse(d.updated)>6*3600000?' · Eldre varsel – venter på oppdatering.':'');
  last=now;
 } catch(e) {
  if(id!==serial)return;
  status.textContent='Værvarselet er midlertidig utilgjengelig. Prøv igjen litt senere.';
  result.hidden=true;
 } finally {clearTimeout(timer);}
}
function choose(x){
 if(!valid(x))return;
 locationRequest++;
 chosen=x;syncSelect();
 const locationPanel=root.querySelector('.rr-weather-location');if(locationPanel){locationPanel.open=false;select.focus();}
 // Keep the location in memory only; each visit starts with device position.
 load();
}
select.addEventListener('change',()=>{if(select.value!=='custom')choose({place:select.value,name:select.selectedOptions[0].textContent});});
const form=root.querySelector('.rr-weather-search'),query=root.querySelector('#rr-place-query'),found=root.querySelector('.rr-place-results'),searchStatus=root.querySelector('.rr-search-status');
let searchController,searchSerial=0;
form.addEventListener('submit',async e=>{
 e.preventDefault();
 const q=query.value.trim();if(q.length<2)return;
 const id=++searchSerial;if(searchController)searchController.abort();searchController=new AbortController();
 const timer=setTimeout(()=>searchController.abort(),18000);
 found.replaceChildren();searchStatus.textContent='Søker etter steder …';
 try{
  const u=new URL(root.dataset.endpoint,location.href);u.searchParams.set('action','rr_weather_search');u.searchParams.set('q',q);
  const r=await fetch(u,{signal:searchController.signal,cache:'no-store',credentials:'same-origin'}),j=await r.json();
  if(id!==searchSerial)return;
  if(!r.ok||!j.success||!Array.isArray(j.data))throw Error();
  for(const x of j.data){
   const place={name:x.name+(x.municipality?' – '+x.municipality:''),lat:Number(x.lat),lon:Number(x.lon)};
   if(!valid(place))continue;
   const li=document.createElement('li'),b=document.createElement('button');b.type='button';
   b.textContent=place.name+(x.type?' ('+x.type+')':'');
   b.addEventListener('click',()=>{choose(place);found.replaceChildren();searchStatus.textContent='Valgt: '+place.name;select.focus();});
   li.append(b);found.append(li);
  }
  searchStatus.textContent=found.children.length?'Velg stedet ditt nedenfor.':'Ingen treff. Prøv et annet stedsnavn.';
 }catch(e){if(id===searchSerial)searchStatus.textContent='Kunne ikke hente stedene. Prøv igjen om litt.';}
 finally{clearTimeout(timer);}
});
root.querySelector('.rr-weather-reset').addEventListener('click',()=>{choose({place:'rubbestadneset',name:'Rubbestadneset'});try{localStorage.removeItem('rr-weather-place-v1');}catch(e){}found.replaceChildren();searchStatus.textContent='';});
function locate(automatic=false){
 const request=++locationRequest;
 const fallback=()=>{
  if(request!==locationRequest)return;
  chosen={place:'rubbestadneset',name:'Rubbestadneset'};syncSelect();load();
  searchStatus.textContent='Viser Rubbestadneset. Du kan velge et annet sted.';
 };
 if(!navigator.geolocation||!window.isSecureContext){fallback();return;}
 if(automatic)status.textContent='Finner posisjonen din …';
 searchStatus.textContent='Venter på tillatelse til å bruke posisjonen din …';
 navigator.geolocation.getCurrentPosition(p=>{
  if(request!==locationRequest)return;
  const x={name:'Min posisjon',lat:Math.round(p.coords.latitude*1000)/1000,lon:Math.round(p.coords.longitude*1000)/1000};
  if(!valid(x)){fallback();return;}
  chosen=x;syncSelect();load();
  searchStatus.textContent='Viser varselet nær posisjonen din.';
 },fallback,{enableHighAccuracy:true,timeout:8000,maximumAge:60000});
}
root.querySelector('.rr-weather-geolocate').addEventListener('click',()=>locate());
document.addEventListener('visibilitychange',()=>{if(!document.hidden&&Date.now()-last>1800000)load();});
setInterval(()=>{if(!document.hidden)load();},1800000);
locate(true);
})();