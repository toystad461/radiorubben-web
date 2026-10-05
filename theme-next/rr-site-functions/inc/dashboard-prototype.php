<?php
defined('ABSPATH') || exit;
// Migrated from active Code Snippets #9. Earlier priority prevents the legacy callback from reaching old theme paths.
/* Radio Rubben dashboard prototype v1. Only /dashboard_test; reuse live renderer and handlers. */
add_action('template_redirect', static function () {
    $path = rtrim(wp_parse_url(wp_unslash($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '', '/');
    if ($path !== rtrim(wp_parse_url(home_url('/dashboard_test/'), PHP_URL_PATH), '/')) return;
    if (!is_user_logged_in()) { auth_redirect(); exit; }
    if (!current_user_can('manage_options')) wp_die('Kun tilgjengelig for administratorer.', 'Ingen tilgang', ['response'=>403]);
    if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
    nocache_headers(); status_header(200); header('X-Robots-Tag: noindex, nofollow', true);
    global $wp_query;
    if ($wp_query instanceof WP_Query) { $wp_query->is_404=false; $wp_query->is_page=true; }
    $_GET['rr_poll_control']='1'; $_GET['rr_dashboard_section']='oversikt';
    add_filter('pre_get_document_title', static fn()=> 'Dashboard · Prototype v1 – Radio Rubben', 999);
    add_filter('rank_math/frontend/title', static fn()=> 'Dashboard · Prototype v1 – Radio Rubben', 999);
    // Existing POST handlers keep their nonce/permission checks and return to the test route.
    add_filter('wp_redirect', static function($url) {
        $parts=wp_parse_url($url);
        if (($parts['host']??'')===wp_parse_url(home_url(),PHP_URL_HOST) && preg_match('~^/dashboard(?:/|$)~', $parts['path']??''))
            return home_url('/dashboard_test/').(!empty($parts['query'])?'?'.$parts['query']:'');
        return $url;
    });
    ob_start();
    require RR_SITE_DIR.'inc/bremnes-poll-test.php';
    $html=ob_get_clean();
    $data=['match'=>$rr_match,'refs'=>$rr_welcome_info['refs']??[],'sponsor'=>$rr_match_sponsor,
        'logo'=>$rr_sponsor_logo_id ? wp_get_attachment_image_url($rr_sponsor_logo_id,'medium') : '',
        'ready'=>rr_poll_lineup_ready($rr_match),'user'=>get_current_user_id()];
    // Keep the existing welcome generator, adding the already imported lineups when available.
    if ($rr_welcome_script!=='') {
        $lineups=[];
        foreach (['home','away'] as $side) {
            $names=$side==='home'?$rr_roster:($rr_match['away_roster']??[]);
            $starters=$side==='home'?($rr_match['starters']??[]):($rr_match['away_starters']??[]);
            if ($starters) {
                $players=[]; foreach($starters as $number) if(isset($names[$number])) $players[]='Nummer '.$number.', '.$names[$number].'.';
                if($players) $lineups[]='Startoppstillingen til '.$rr_match[$side].': '.implode(' ',$players);
            }
        }
        $rr_v1_script=str_replace('Kampstart er klokken .','Kampstart er klokken '.wp_date('H.i',strtotime($rr_match['kickoff']),new DateTimeZone('Europe/Oslo')).'.',$rr_welcome_script);
        if($lineups) $rr_v1_script.="\n\n".implode("\n\n",$lineups);
        $html=str_replace(esc_textarea($rr_welcome_script),esc_textarea($rr_v1_script),$html);
    }
    $css= <<<'CSS'
<style id="rr-dashboard-v1-style">
#rr-dash-v1{max-width:1440px;width:calc(100% - 48px);margin:28px auto 100px;color:#f4f5f8}
#rr-dash-v1 *{box-sizing:border-box}#rr-dash-v1 .v1-head{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:20px}
#rr-dash-v1 .v1-head h1{display:block;margin:4px 0;font-size:32px;letter-spacing:-.04em}#rr-dash-v1 .v1-kicker{color:#f5cb45;font-size:11px;font-weight:850;letter-spacing:.14em;text-transform:uppercase}
#rr-dash-v1 .v1-head p{margin:4px 0;color:#aab8cd;font-size:13px}#rr-dash-v1 .v1-badge{border:1px solid #65592d;border-radius:24px;background:#2d281a;color:#ffe38c;padding:8px 13px;white-space:nowrap;font-size:12px;font-weight:750}
#rr-dash-v1 .v1-columns{display:grid;grid-template-columns:minmax(0,62fr) minmax(0,38fr);gap:24px;align-items:start}
#rr-dash-v1 .v1-column{min-width:0;scroll-margin-top:calc(var(--v1-top,238px) + 66px)}#rr-dash-v1 .v1-column-title{font-size:13px;letter-spacing:.13em;margin:0 0 14px;color:#c2cede;text-transform:uppercase}
#rr-dash-v1 .v1-speaker{position:sticky;top:var(--v1-top,238px);max-height:calc(100dvh - var(--v1-top,238px) - 64px);overflow-y:auto;overscroll-behavior:contain;scrollbar-width:thin;padding:18px;background:#0d1420;border:1px solid #35445b;border-radius:18px}
#rr-dash-v1 .v1-speaker .v1-column-title{color:#f5cb45}#rr-dash-v1 .v1-card{padding:22px;margin:0 0 18px;border:1px solid #35445b;border-radius:15px;background:#171f2d;min-width:0}
#rr-dash-v1 .v1-speaker .v1-card{padding:16px;margin-bottom:12px;background:#141d2b;border-color:#2e3b50}#rr-dash-v1 .v1-card:last-child{margin-bottom:0}
#rr-dash-v1 h2{font-size:19px;line-height:1.3;margin:0 0 16px}#rr-dash-v1 h3{font-size:14px;margin:16px 0 10px}#rr-dash-v1 p{line-height:1.55}#rr-dash-v1 .v1-muted{color:#aab8cd;font-size:13px;margin:8px 0}
#rr-dash-v1 button,#rr-dash-v1 input,#rr-dash-v1 select{min-height:44px;font:inherit}#rr-dash-v1 button{border-radius:9px;font-size:13px;padding:10px 13px;font-weight:750;cursor:pointer}#rr-dash-v1 button:disabled{opacity:.42;cursor:not-allowed}
#rr-dash-v1 button:focus-visible,#rr-dash-v1 a:focus-visible,#rr-dash-v1 textarea:focus-visible,#rr-dash-v1 summary:focus-visible{outline:3px solid #f5cb45;outline-offset:3px}
#rr-dash-v1 .v1-actions{display:flex;gap:8px;flex-wrap:wrap;margin:14px 0}#rr-dash-v1 .v1-primary{background:#f5cb45;color:#111927;border:1px solid #f5cb45}
#rr-dash-v1 #poll-match-heading{display:block;text-align:left}#rr-dash-v1 .poll-match .poll-teams{max-width:540px;margin:18px auto}#rr-dash-v1 .poll-team img{width:64px;height:64px;object-fit:contain}#rr-dash-v1 .poll-team strong{font-size:23px}
#rr-dash-v1 .poll-details{font-size:14px!important;text-align:center}#rr-dash-v1 .poll-timer{margin-top:12px!important}#rr-dash-v1 #poll-clock{font-size:34px}#rr-dash-v1 .speaker-step{margin-top:12px}#rr-dash-v1 .speaker-step>summary{display:block;font-size:14px;font-weight:750;padding:13px}
#rr-dash-v1 .speaker-compact-form{grid-template-columns:minmax(0,1fr) auto}#rr-dash-v1 .speaker-compact-form input{min-width:0}#rr-dash-v1 .speaker-compact-form input[type=file]{grid-column:1/-1;max-width:100%;font-size:12px}
#rr-dash-v1 .v1-checks{list-style:none;padding:0;margin:0 0 14px;display:grid;gap:7px;font-size:12px;color:#c6d0de}#rr-dash-v1 .v1-checks li{padding:0;border:0;margin:0;display:flex;justify-content:space-between;gap:10px}#rr-dash-v1 .v1-checks span{color:#99ddb9}#rr-dash-v1 .v1-checks .v1-missing{color:#ffce7a}
#rr-dash-v1 .v1-welcome .speaker-step{border:0;margin:0;background:none}#rr-dash-v1 .v1-welcome .speaker-step>summary{display:none}#rr-dash-v1 .v1-welcome form{display:block;padding:0;border:0}#rr-dash-v1 .v1-welcome form button{width:100%;background:#f5cb45;color:#121923}
#rr-dash-v1 .speaker-step textarea{width:100%;margin:12px 0}#rr-dash-v1 .speaker-field-grid{grid-template-columns:1fr;gap:8px}#rr-dash-v1 .speaker-field-grid select{font-size:14px;min-height:44px}
#rr-dash-v1 #speaker-quick{display:block!important;border:0;padding:0}#rr-dash-v1 .speaker-event-buttons{grid-template-columns:1fr 1fr;gap:8px;margin:12px 0}#rr-dash-v1 .speaker-event-buttons button{min-height:48px;font-size:13px}#rr-dash-v1 [data-event=red]{background:#54202b}#rr-dash-v1 [data-event=yellow]{background:#e8bd40;color:#151b24}
#rr-dash-v1 #speaker-sub-panel{grid-template-columns:1fr}#rr-dash-v1 #speaker-card-toggle{display:none}#rr-dash-v1 .v1-fixed{display:grid;grid-template-columns:1fr 1fr;gap:8px}#rr-dash-v1 .v1-fixed button{text-align:left;min-height:48px;background:#202c3f}
#rr-dash-v1 .v1-mini{display:flex;align-items:center;gap:10px}#rr-dash-v1 .v1-mini img{width:32px;height:32px;object-fit:contain}#rr-dash-v1 .v1-mini strong{font-size:16px}#rr-dash-v1 .v1-mini-status{color:#f5cb45;font-size:12px;margin:10px 0 0}
#rr-dash-v1 .v1-refs{list-style:none;margin:0;padding:0}#rr-dash-v1 .v1-refs li{padding:7px 0;border-bottom:1px solid #2e3b50;font-size:13px}#rr-dash-v1 .v1-refs small{display:block;color:#aab8cd;font-size:11px}
#rr-dash-v1 .v1-roster{list-style:none;padding:0;margin:0}#rr-dash-v1 .v1-roster li{display:flex;justify-content:space-between;gap:12px;padding:11px 0;border-bottom:1px solid #303e52;font-size:14px}#rr-dash-v1 .v1-roster small{color:#9fceb4;font-size:12px;text-align:right}
#rr-dash-v1 .v1-sponsor-logo{max-width:220px;max-height:100px;object-fit:contain;background:#fff;padding:12px;border-radius:10px;margin-bottom:12px}#rr-dash-v1 .v1-note{width:100%;min-height:140px;resize:vertical;background:#0b1422;border:1px solid #506078;border-radius:10px;color:#fff;font:inherit;font-size:14px;padding:12px;line-height:1.6}
#rr-dash-v1 .v1-nav{display:flex;gap:8px;margin:0 0 20px}#rr-dash-v1 .v1-nav a{padding:10px 16px;background:#1b2739;border:1px solid #394962;border-radius:9px;text-decoration:none;color:#ecf1f7;font-size:12px;font-weight:800}
#rr-dash-v1 #poll-feedback:not(:empty){position:static}#rr-dash-v1 #poll-live{display:none}#rr-dash-v1 .speaker-poll-panel{max-height:340px;overflow:auto}#rr-dash-v1 .poll-event-row{min-height:58px}#rr-dash-v1 .speaker-primary-row{margin:14px 0 0}#rr-dash-v1 .speaker-main-command{font-size:14px;min-height:46px}#rr-dash-v1 .speaker-undo{min-width:70px}
@media(max-width:1000px){#rr-dash-v1{width:calc(100% - 28px)}#rr-dash-v1 .v1-columns{grid-template-columns:1fr}#rr-dash-v1 .v1-speaker{position:static;max-height:none;overflow:visible}#rr-dash-v1 .v1-nav{position:sticky;top:var(--v1-top,238px);z-index:30;background:#101722;padding:8px 0}#rr-dash-v1 .v1-nav a{flex:1;text-align:center}}
@media(max-width:520px){#rr-dash-v1 .v1-head{align-items:flex-start}#rr-dash-v1 .v1-head h1{font-size:26px}#rr-dash-v1 .v1-badge{font-size:10px;padding:7px 9px}#rr-dash-v1 .v1-card{padding:16px}#rr-dash-v1 .v1-speaker{padding:12px}#rr-dash-v1 .poll-team strong{font-size:20px}#rr-dash-v1 .speaker-compact-form{grid-template-columns:1fr}#rr-dash-v1 .v1-roster li{font-size:13px}#rr-dash-v1 .v1-fixed{grid-template-columns:1fr}#rr-dash-v1 .speaker-primary-row{grid-template-columns:1fr}#rr-dash-v1 .v1-actions>button{flex:1}}
</style>
CSS;
    $js= <<<'JS'
<script id="rr-dashboard-v1-script">
(()=>{
'use strict';
const d=RR_DASH_V1_DATA, $=s=>document.querySelector(s), el=id=>document.getElementById(id), main=$('.rr-speaker');
if(!main)return; main.id='rr-dash-v1';
const siteHeader=document.querySelector('.rr-header');const sizeLayout=()=>{const top=siteHeader?siteHeader.getBoundingClientRect().height+(parseFloat(getComputedStyle(siteHeader).top)||0)+14:20;main.style.setProperty('--v1-top',top+'px');};sizeLayout();if(siteHeader&&window.ResizeObserver)new ResizeObserver(sizeLayout).observe(siteHeader);window.addEventListener('resize',sizeLayout);
const make=(tag,cls,text)=>{const n=document.createElement(tag);if(cls)n.className=cls;if(text!==undefined)n.textContent=text;return n;};
const move=(selector,parent)=>{const n=$(selector);if(n)parent.append(n);return n;};
const card=(parent,title,cls='')=>{const n=make('section','v1-card '+cls);n.append(make('h2','',title));parent.append(n);return n;};
const button=(text,fn,cls='')=>{const b=make('button',cls,text);b.type='button';b.onclick=fn;return b;};
const header=make('header','v1-head');const intro=make('div');intro.append(make('span','v1-kicker','Radio Rubben · Kampdag'),make('h1','','Dashboard'),make('p','','Testvisning med eksisterende kampdata. Handlinger gjelder valgt kamp.'));header.append(intro,make('span','v1-badge','PROTOTYPE V1'));
const nav=make('nav','v1-nav');nav.setAttribute('aria-label','Dashboard-visning');for(const [id,title] of [['v1-admin','KAMPADMIN'],['v1-speaker','SPEAKER']]){const a=make('a','',title);a.href='#'+id;nav.append(a);}
const columns=make('div','v1-columns'),left=make('div','v1-column'),right=make('aside','v1-column v1-speaker');left.id='v1-admin';right.id='v1-speaker';right.setAttribute('aria-label','Speaker');left.append(make('h2','v1-column-title','Kampadministrasjon'));right.append(make('h2','v1-column-title','🎙 Speaker'));columns.append(left,right);
main.prepend(header,nav,columns);main.querySelector(':scope > .tag')?.remove();main.querySelector(':scope > h1')?.remove();main.querySelector(':scope > .speaker-nav')?.remove();move('#poll-feedback',left);
const match=move('.poll-match',left);match.classList.add('v1-card');el('poll-match-heading').textContent='Dagens kamp';match.append(make('p','v1-muted',d.match.team+' · '+d.match.competition+' · FIKS-ID '+d.match.id));
const steps=[...main.querySelectorAll('#speaker-workflow > .speaker-step')];if(steps[0]){match.append(steps[0]);steps[0].querySelector('summary').textContent='Bytt kamp';}
const vote=card(left,'Avstemming');move('#poll-status',vote);move('.poll-vote-deadline',vote);const status=move('.speaker-live-status',vote);const total=make('strong','', 'Henter antall stemmer …');total.id='v1-total';vote.append(total);
const actions=make('div','v1-actions');const open=button('Åpne avstemming',()=>{const b=el('speaker-main-command');if(b.dataset.action==='start'&&!b.disabled)b.click();},'v1-primary');open.disabled=true;open.id='v1-open';actions.append(open);move('[data-command="close"]',actions);actions.append(button('Resultat',()=>{el('speaker-poll-summary').click();}));vote.append(actions);move('#speaker-poll-panel',vote);vote.append(make('p','v1-muted','Åpne starter også kampklokken. Avstemmingen stenger ved 85:00. Kåring og stemmevinner vises ved 86:00. Vinneren trekkes blant alle som har stemt.'));move('.speaker-primary-row',vote);
const players=card(left,'Spillere og innbyttere');players.append(make('p','v1-muted','Startspillere blir stemmeberettiget ved kampstart. Innbyttere blir valgbare etter registrert bytte.'));const roster=make('div');roster.id='v1-rosters';players.append(roster);move('.speaker-tools',players);
const sponsor=card(left,'Kampsponsor');if(d.logo){const img=make('img','v1-sponsor-logo');img.src=d.logo;img.alt=d.sponsor;sponsor.append(img);}sponsor.append(make('strong','',d.sponsor||'Ingen kampsponsor valgt'));if(steps[1]){sponsor.append(steps[1]);steps[1].querySelector('summary').textContent='Endre kampsponsor';}
const events=card(left,'Hendelser');events.id='v1-events';events.append(make('p','v1-muted','Velg en hendelse for speakerforslag. Nyeste vises øverst.'));const empty=make('p','v1-muted','Ingen hendelser registrert ennå.');empty.id='poll-events-empty';const list=make('ol');list.id='poll-events-list';list.setAttribute('aria-label','Kamphendelser, nyeste først');const credit=make('p','v1-muted');credit.id='poll-event-credit';events.append(empty,list,credit);move('#nff-auto-status',events);
const compact=card(right,'Kampen');const mini=make('div','v1-mini');for(const side of ['home','away']){if(d.match[side+'_logo']){const img=make('img');img.src=d.match[side+'_logo'];img.alt=d.match[side];mini.append(img);}mini.append(make('strong','',d.match[side]));if(side==='home')mini.append(make('span','','–'));}compact.append(mini,make('p','v1-muted',d.match.date_label+' · '+d.match.venue));const miniStatus=make('p','v1-mini-status','Henter kampstatus …');compact.append(miniStatus);
const welcome=card(right,'Velkomstmanus','v1-welcome');const checks=make('ul','v1-checks');const readiness=(name,value,ok=true)=>{const li=make('li');li.append(make('strong','',name),make('span',ok?'':'v1-missing',value));checks.append(li);};readiness('Kamp','✓ Klar');readiness('Lagoppstillinger',d.ready&&d.match.away_starters?.length?'✓ Begge lag':d.ready?'⚠ Motstander mangler':'⚠ Mangler',!!(d.ready&&d.match.away_starters?.length));readiness('Dommere',d.refs.length?'✓ '+d.refs.length+' registrert':'⚠ Mangler',!!d.refs.length);readiness('Kampsponsor',d.sponsor?'✓ Valgt':'⚠ Mangler',!!d.sponsor);readiness('Dagens Bremnesing','✓ Inkludert');readiness('Odd Inges Minnefond','✓ Inkludert');welcome.append(checks);if(steps[2]){welcome.append(steps[2]);steps[2].open=true;steps[2].querySelector('[name=rr_welcome_generate]').textContent='Generer velkomstmanus';}
if(!d.ready)welcome.append(make('p','v1-muted','Manuset kan genereres nå. Manglende lagoppstillinger må hentes før spillerpresentasjonen.'));
const refs=card(right,'Dommere');const refsList=make('ul','v1-refs');for(const r of d.refs){const li=make('li');li.append(make('small','',r.role),make('strong','',r.name));refsList.append(li);}refs.append(d.refs.length?refsList:make('p','v1-muted','Dommere er ikke publisert ennå.'));$('.speaker-referees')?.remove();
const quick=card(right,'Hurtigstikk');quick.append(make('p','v1-muted','Velg lag og spiller for å registrere mål, bytte eller kort.'));const quickBody=move('#speaker-quick',quick);quickBody.querySelector('.speaker-stage-label')?.remove();const quickButtons=quickBody.querySelector('.speaker-event-buttons');move('#speaker-sub-toggle',quickButtons);for(const [type,label] of [['yellow','🟨 Gult kort'],['red','🟥 Rødt kort']]){const b=move('[data-event="'+type+'"]',quickButtons);b.textContent=label;}
const pause=button('⏱ Pause',()=>el('speaker-main-command').click());const finish=button('🏁 Slutt',()=>el('speaker-main-command').click());pause.disabled=finish.disabled=true;quickButtons.append(pause,finish);
const fixed=card(right,'Faste meldinger'),fixedGrid=make('div','v1-fixed');fixed.append(fixedGrid);let latest=null;
function showText(title,text,trigger){el('speaker-comment-title').textContent=title;el('speaker-comment-context').textContent=d.match.home+' – '+d.match.away;el('speaker-comment-text').value=text;el('speaker-comment-feedback').textContent='';const dialog=el('speaker-comment-dialog');dialog.addEventListener('close',()=>trigger.focus(),{once:true});dialog.showModal();el('speaker-comment-text').focus();}
for(const title of ['Dagens Bremnesing','Kampsponsor','Odd Inges Minnefond','Radio Rubben']){const b=button(title,()=>{let text='';if(title==='Dagens Bremnesing'){const award=latest?.match_events?.find(e=>e.type==='award');text=award?'Dagens Bremnesing: '+award.description+'. Gratulerer!':latest?.closed?'Avstemmingen på Dagens Bremnesing er stengt. Takk til alle som har stemt!':latest?.opened?'Hvem blir Dagens Bremnesing? Gå til radiorubben.no/kamp, logg inn med Vipps og stem på din favoritt. Det er gratis, og du kan stemme én gang.':'Når kampen starter, åpner avstemmingen på Dagens Bremnesing. Gå til radiorubben.no/kamp og logg inn med Vipps. Avstemmingen stenger ved 85 minutter.';}if(title==='Kampsponsor')text=d.sponsor?'Dagens kampsponsor er '+d.sponsor+'. Tusen takk for støtten til klubben og dagens kamp!':'Kampsponsor er ikke valgt. Legg inn sponsor før opplesning.';if(title==='Odd Inges Minnefond')text='Vi vil også minne om Odd-Inges minnefond, som støtter blant annet stipend, dommerutstyr og behov i Bremnes Idrettslag. Vil du bidra, kan du vippse til 626873. Takk for støtten!';if(title==='Radio Rubben')text='Du lytter til Radio Rubben. Ingen valg. Bare god radio. Finn oss på radiorubben.no.';showText(title,text,b);});fixedGrid.append(b);}
move('#speaker-vote-cue',fixed);
const notes=card(right,'Speakernotat');const label=make('label','','Egne notater til kampen');label.htmlFor='v1-note';const note=make('textarea','v1-note');note.id='v1-note';note.placeholder='Navn, uttale, påminnelser …';const noteState=make('p','v1-muted','Lagres i denne nettleseren for denne kampen.');const noteKey='rr-dashboard-test-note:'+d.user+':'+d.match.id;try{note.value=localStorage.getItem(noteKey)||'';}catch(e){noteState.textContent='Lokal lagring er ikke tilgjengelig.';}note.addEventListener('input',()=>{try{localStorage.setItem(noteKey,note.value);noteState.textContent='Lagret i denne nettleseren.';}catch(e){noteState.textContent='Kunne ikke lagre. Kopier notatet før du lukker siden.';}});notes.append(label,note,noteState);move('#speaker-comment-dialog',main);
const copy=el('speaker-welcome-copy');if(copy)copy.onclick=async()=>{try{await navigator.clipboard.writeText(el('speaker-welcome-text').value);el('speaker-welcome-copy-status').textContent=' Kopiert.';}catch(e){el('speaker-welcome-text').focus();el('speaker-welcome-text').select();el('speaker-welcome-copy-status').textContent=' Velg Kopier.';}};
main.querySelectorAll('form').forEach(f=>{const url=new URL(f.action,location.href);if(url.origin===location.origin&&/^\/dashboard(?:\/|$)/.test(url.pathname)){url.pathname='/dashboard_test/';f.action=url.href;}});const nff=el('speaker-nff-status');if(nff)nff.href='#v1-events';
let signature='';function renderRosters(s){const key=JSON.stringify([s.candidates,s.entered,s.opened,s.closed]);if(key===signature)return;signature=key;roster.replaceChildren();for(const [title,numbers] of [['Startspillere',d.match.starters||[]],['Innbyttere',d.match.bench||[]]]){roster.append(make('h3','',title));if(!numbers.length){roster.append(make('p','v1-muted','Ikke publisert eller hentet ennå.'));continue;}const ul=make('ul','v1-roster');for(const no of numbers){const li=make('li');const allowed=Object.prototype.hasOwnProperty.call(s.candidates||{},no);li.append(make('span','','#'+no+'  '+(d.match.roster[no]||'Ukjent spiller')),make('small','',s.closed?'Avstemming stengt':allowed&&s.opened?'✓ Stemmeberettiget':title==='Startspillere'?'Klar ved kampstart':s.entered?.[no]?'Byttet inn':'Ikke byttet inn'));ul.append(li);}roster.append(ul);}}
renderRosters({candidates:{},entered:{},opened:false,closed:false});
document.addEventListener('rr-dashboard-v1-state',e=>{const s=e.detail;latest=s;total.textContent=(s.results||[]).reduce((sum,r)=>sum+Number(r.total||0),0)+' stemmer';open.disabled=s.busy||!s.healthy||s.opened||s.closed||!s.roster_ready||s.finished;pause.disabled=s.busy||!s.healthy||s.period!==1||!s.running||s.finished;finish.disabled=s.busy||!s.healthy||s.period!==2||s.finished;miniStatus.textContent=!s.healthy?'Forbindelsen er brutt':el('poll-clock-label').textContent+' · '+el('poll-clock').textContent+(s.opened?' · '+s.score.home+'–'+s.score.away:'');renderRosters(s);});
})();
</script>
JS;
    $js=str_replace('RR_DASH_V1_DATA',wp_json_encode($data,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT),$js);
    // Test-only adapter: expose existing state without another polling loop or API implementation.
    $html=str_replace('updateLiveStatus(elapsed,closed);','updateLiveStatus(elapsed,closed); document.dispatchEvent(new CustomEvent("rr-dashboard-v1-state",{detail:{...state,closed,healthy,busy}}));',$html);
    $marker="<script>\n(()=>{";
    if (strpos($html,$marker)===false) wp_die('Testvisningen må oppdateres for den nye kampmalen. Produksjonen er urørt.');
    $html=str_replace($marker,$css.$js.$marker,$html);
    echo $html;
    exit;
}, -21);