(function(){
"use strict";
var root=document.querySelector(".rr-test");if(!root)return;
var busy=false;
function rows(){return Array.from(root.querySelectorAll(".rr-test-row[data-player-id]"));}
function active(row,now){var k=Number(row.dataset.kickoff);if(k<=0)return false;if(now<k-4500)return false;return now<=k+14400;}
function resetExpired(now){rows().forEach(function(row){
var b=row.querySelector(".rr-test-badge");
if(b ? (b.dataset.expires ? Number(b.dataset.expires)<=now : false) : false){b.textContent="Status oppdateres";b.style.background="";delete b.dataset.expires;b.title="Venter på fersk spillerstatus";}
var center=row.querySelector(".rr-test-time");
if(center ? (center.dataset.liveExpires ? Number(center.dataset.liveExpires)<=now : false) : false){
center.innerHTML=center.dataset.originalMarkup;delete center.dataset.liveExpires;delete center.dataset.originalMarkup;
}
});}

function paintMatch(row,m,now){
if(!m)return false;
if(Number(m.expires)<=now)return false;
if(Date.parse(m.kickoff)/1000!==Number(row.dataset.kickoff))return false;
var names=row.querySelectorAll(".rr-test-team small");
if(names.length!==2)return false;
if(names[0].textContent.trim()!==m.home.name||names[1].textContent.trim()!==m.away.name)return false;
var phase={live:"Pågår",finished:"Ferdigspilt",cancelled:"Avlyst / avbrutt"}[m.phase]||"";
var score=m.score,valid=false;
if(score)valid=Number.isInteger(score.home)?Number.isInteger(score.away):false;
if(valid)valid=score.home>=0?score.away>=0:false;
if(!phase ? !valid : false)return false;
var center=row.querySelector(".rr-test-time");if(!center)return false;
if(!center.dataset.originalMarkup)center.dataset.originalMarkup=center.innerHTML;
center.dataset.liveExpires=String(m.expires);
center.replaceChildren();
var result=document.createElement("strong"),label=document.createElement("span"),stamp=document.createElement("small");
result.textContent=valid?score.home+"–"+score.away:"–";
result.setAttribute("aria-label",valid?"Hjemme "+score.home+", borte "+score.away:"Resultat ikke tilgjengelig");
label.textContent=valid?(score.kind==="halftime"?"Pauseresultat":phase||"Registrert stilling"):phase;
stamp.textContent=valid?"Kontrollert "+new Date(m.checked_at*1000).toLocaleTimeString("nb-NO",{hour:"2-digit",minute:"2-digit"}):"Resultat ikke tilgjengelig";
stamp.style.cssText="display:block;font-size:10px;color:#a8b1b9;white-space:normal";
label.style.color=m.phase==="live"?"#7ee2a8":"#bac1c8";
center.append(result,label,stamp);
var badge=row.querySelector(".rr-test-badge");
if(badge ? ["finished","cancelled"].includes(m.phase) : false){
badge.textContent=phase;badge.dataset.expires=String(m.expires);badge.style.background="#46515c";
}else if(badge ? m.phase==="live" : false){
badge.textContent="Pågår";badge.dataset.expires=String(m.expires);badge.style.background="#23744b";
}
return true;
}

async function refresh(){
var now=Date.now()/1000;resetExpired(now);
if(busy||document.hidden||!rows().some(function(r){return active(r,now);}))return;
busy=true;
try{
var response=await fetch("/wp-json/rr-player-widget/v1/widget",{cache:"no-store",credentials:"same-origin",signal:AbortSignal.timeout(12000)});
if(!response.ok)throw new Error("status");
var data=await response.json();if(typeof data.html!=="string")throw new Error("data");
var doc=new DOMParser().parseFromString(data.html,"text/html"),liveMatches={},matches={};
if(Array.isArray(data.matches))data.matches.forEach(function(m){matches[String(m.id)]=m;});
now=Date.now()/1000;
doc.querySelectorAll("article[data-player-id]").forEach(function(source){
var link=source.querySelector(".rrpw-mini-nff"),status=source.querySelector(".rrpw-mini-status");
if(!link||!status||status.hidden||Number(status.dataset.lineupExpires)<=now)return;
var match=new URL(link.getAttribute("href"),location.origin).searchParams.get("fiksId");
if(status.textContent.trim().toLowerCase()==="spiller nå")liveMatches[match]=Math.max(liveMatches[match]||0,Number(status.dataset.lineupExpires));
});
rows().forEach(function(row){
if(!active(row,now))return;
var painted=paintMatch(row,matches[row.dataset.matchId],now);
var source=doc.querySelector('article[data-player-id="'+row.dataset.playerId+'"]');
if(!source)return;
var link=source.querySelector(".rrpw-mini-nff");if(!link)return;
var match=new URL(link.getAttribute("href"),location.origin).searchParams.get("fiksId");
if(match!==row.dataset.matchId)return;
var k=Number(source.dataset.kickoff);if(k>0)row.dataset.kickoff=String(k);
var status=source.querySelector(".rrpw-mini-status"),b=row.querySelector(".rr-test-badge"),center=row.querySelector(".rr-test-time");
if(!painted ? (center ? Boolean(liveMatches[match]) : false) : false){
if(!center.dataset.originalMarkup)center.dataset.originalMarkup=center.innerHTML;
center.dataset.liveExpires=String(liveMatches[match]);
center.innerHTML='<strong aria-label="Resultat ikke tilgjengelig">–</strong><span style="color:#7ee2a8">Pågår</span><small style="display:block;font-size:10px;color:#a8b1b9">Resultat ikke tilgjengelig</small>';
}
if(!b)return;
var expires=(status ? !status.hidden : false)?Number(status.dataset.lineupExpires):0;
var text=expires>now?status.textContent.trim().toLowerCase():"",label="";
if(text==="startellever"||text==="i startelleveren")label="I startelleveren";
else if(text==="på benken"||text==="benk"||text==="innbytter")label="Innbytter";
else if(text==="spiller nå")label="Spiller nå";
else if(text==="byttet ut")label="Byttet ut";
else if(text==="utvisning")label="Utvisning";
if(!label ? Boolean(liveMatches[match]) : false){label="Pågår";expires=liveMatches[match];}
if(!label)return;
b.textContent=label;b.dataset.expires=String(expires);
b.style.background=["Spiller nå","Pågår","I startelleveren"].includes(label)?"#23744b":"#46515c";
b.title=(label==="Pågår"?"Bekreftet kampstatus":"Bekreftet spillerstatus")+" · hentet "+new Date().toLocaleTimeString("nb-NO",{hour:"2-digit",minute:"2-digit"});
});
}catch(e){}finally{busy=false;}
}
refresh();setInterval(refresh,60000);setInterval(function(){resetExpired(Date.now()/1000);},10000);
document.addEventListener("visibilitychange",function(){if(!document.hidden)refresh();});
})();
