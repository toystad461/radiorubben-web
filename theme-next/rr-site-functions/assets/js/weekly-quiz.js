(() => {
  'use strict';
  const root = document.querySelector('#rr-weekly.rrq');
  if (!root || typeof rrwqConfig === 'undefined') return;
  const status = root.querySelector('.rrq-status'), play = root.querySelector('.rrq-play'), board = root.querySelector('.rrq-board');
  let state, answers = Array(20).fill(null), index = 0, tick, busy = false, serverNow = 0, syncedAt = 0;
  const el = (tag, text, cls) => { const n = document.createElement(tag); if (text !== undefined) n.textContent = text; if (cls) n.className = cls; return n; };
  const duration = seconds => { const s = Math.max(0, Math.floor(seconds)); return Math.floor(s/60) + ':' + String(s%60).padStart(2,'0'); };
  const storageKey = () => 'rrwq:' + state.week + ':' + state.storageKey;
  function save() { try { sessionStorage.setItem(storageKey(), JSON.stringify(answers)); } catch (_) {} }
  function load() { try { const a = JSON.parse(sessionStorage.getItem(storageKey())); if (Array.isArray(a) && a.length === 20 && a.every(x => x === null || (Number.isInteger(x) && x >= 0 && x <= 3))) answers = a; } catch (_) {} }
  async function request(op, extra = {}) {
    const data = new URLSearchParams({action:'rrwq', op, nonce:state?.nonce || '', week:state?.week || '', revision:state?.revision || 1, ...extra});
    const res = await fetch(rrwqConfig.ajax, {method:'POST',credentials:'same-origin',cache:'no-store',body:data});
    let json; try { json = await res.json(); } catch (_) { throw new Error('Kunne ikke hente quizen. Prøv igjen; en startet klokke fortsetter.'); }
    if (!res.ok || !json.success) throw new Error(json.data?.message || 'Kunne ikke fullføre. Last siden på nytt; klokken fortsetter.');
    state = json.data; serverNow = state.now; syncedAt = performance.now();
  }
  function button(text, fn, cls) {
    const b = el('button', text, cls); b.type = 'button';
    b.addEventListener('click', async () => { if (busy) return; busy = true; b.disabled = true; try { await fn(); } catch (e) { status.textContent = e.message; } finally { busy = false; b.disabled = false; } });
    return b;
  }
  let historyState, historyLoading = false;
  function boardTable(rows, overall = false, label = 'Toppliste') {
    const table = el('table'), head = el('thead'), tr = el('tr');
    table.setAttribute('aria-label', label);
    if (overall) table.className = 'rrq-overall-table';
    (overall ? ['Plass','Fornavn','Poeng','Uker','Tid'] : ['Plass','Fornavn','Riktige','Tid']).forEach(t => { const th = el('th',t); th.scope='col'; tr.append(th); });
    head.append(tr); table.append(head); const body = el('tbody');
    rows.forEach((r,i) => { const row = el('tr'); const values = overall ? [i+1,r.name,r.score,r.weeks,duration(r.seconds)] : [i+1,r.name,r.score+'/20',duration(r.seconds)]; values.forEach(v => row.append(el('td',String(v)))); body.append(row); });
    table.append(body);
    if (!overall) return table;
    const scroll = el('div',undefined,'rrq-table-scroll'); scroll.tabIndex=0; scroll.setAttribute('role','region'); scroll.setAttribute('aria-label',label+' – bla sideveis ved behov'); scroll.append(table); return scroll;
  }
  async function historyRequest(op = 'history', extra = {}) {
    const body = new URLSearchParams({action:'rrwq_history',op,nonce:state?.nonce || '',...extra});
    const response = await fetch(rrwqConfig.ajax,{method:'POST',credentials:'same-origin',cache:'no-store',body});
    const json = await response.json();
    if (!response.ok || !json.success) throw new Error(json.data?.message || 'Kunne ikke hente topplistearkivet.');
    historyState = json.data;
  }
  function drawHistory() {
    const host = board.querySelector('.rrq-history'); if (!host) return;
    host.replaceChildren(el('h3','Samlet topp ti'));
    host.append(el('p','Samlet poengsum fra alle uker. Ved lik poengsum avgjør samlet tid. Bare offentlig delte resultater fra spillere som har valgt å bli med i denne listen, telles.'));
    if (!historyState) { host.append(el('p','Henter topplistearkivet …')); return; }
    if (state.loggedIn) {
      host.append(el('p','Valget gjelder dine eksisterende og fremtidige offentlig delte ukesresultater. Private eller skjulte resultater tas aldri med. Du kan trekke valget tilbake her.','rrq-small'));
      host.append(button(historyState.optedIn ? 'Fjern meg fra samlet toppliste' : 'Ta med mine offentlige resultater',async()=>{await historyRequest('consent',{include:historyState.optedIn?'0':'1'});drawHistory();},'rrq-secondary'));
    }
    if (historyState.overall.length) host.append(boardTable(historyState.overall,true,'Samlet topp ti'));
    else host.append(el('p','Ingen har valgt å delta på den samlede topplisten ennå.'));
    const details = el('details',undefined,'rrq-archive'); details.append(el('summary','Tidligere uker'));
    const weeks = historyState.weeks.filter(w=>w.week !== state.week);
    if (!weeks.length) details.append(el('p','Ingen tidligere uker med offentlig delte resultater er tilgjengelige ennå.'));
    else {
      const label=el('label','Velg uke'), select=el('select'); select.id='rrq-archive-week'; label.htmlFor=select.id;
      weeks.forEach(w=>{const option=el('option','Uken fra '+w.label);option.value=w.week;select.append(option);});
      const results=el('div',undefined,'rrq-archive-results');
      const show=()=>{const week=weeks.find(w=>w.week===select.value);results.replaceChildren(el('h4','Toppliste – uken fra '+week.label),boardTable(week.board,false,'Toppliste – uken fra '+week.label));};
      select.addEventListener('change',show);details.append(label,select,results);show();
    }
    host.append(details);
  }
  function drawBoard() {
    board.replaceChildren(el('h3','Ukens topp ti'), el('p','Fornavn vises bare for spillere som har valgt å delta på topplisten.'));
    if (!state.board.length) board.append(el('p','Ingen resultater på topplisten ennå. Førsteplassen er ledig!'));
    else board.append(boardTable(state.board,false,'Ukens topp ti'));
    board.append(el('div',undefined,'rrq-history')); drawHistory();
    if (!historyLoading) {
      historyLoading=true;
      historyRequest().then(drawHistory).catch(()=>{historyLoading=false;const host=board.querySelector('.rrq-history');if(host){host.replaceChildren(el('p','Topplistearkivet kunne ikke lastes.'),button('Prøv å hente arkivet igjen',async()=>{await historyRequest();drawHistory();},'rrq-secondary'));}});
    }
  }
  function render() {
    clearInterval(tick); play.replaceChildren(); drawBoard();
    status.textContent = 'Uken fra ' + state.label + (state.title ? ' · ' + state.title : '');
    if (state.revision > 1) play.append(el('p','Ny utgave: Quizen er nullstilt. Alle kan starte på nytt med 20 nye spørsmål på omtrent nivå 5/10.','rrq-small'));
    if (!state.ready) { play.append(el('p','Ukens spørsmål klargjøres. Kom gjerne tilbake litt senere.')); return; }
    if (!state.loggedIn) {
      const template = document.getElementById('rrq-login-template');
      if (template) play.append(template.content.cloneNode(true));
      else { const login=el('a','Logg inn og spill','rrq-link'); login.href=rrwqConfig.login; play.append(login); }
      return;
    }
    if (!state.verified) { play.append(el('p','Bekreft e-postadressen din før du starter.')); return; }
    if (state.result) { result(); return; }
    if (state.started) { load(); question(); return; }
    const label=el('label','Fornavn (bare hvis du vil vises på topplisten)'), name=el('input');
    name.type='text'; name.name='rrq-first-name'; name.autocomplete='given-name'; name.maxLength=40; label.append(name);
    const choice=el('label',undefined,'rrq-choice'), consent=el('input'); consent.type='checkbox';
    choice.append(consent,el('span','Ja, vis fornavnet mitt, poengsummen og tiden min på ukens offentlige toppliste.'));
    play.append(label,choice,el('p','Du kan spille uten å vises på listen. Spilldata knyttes til kontoen din. Du kan skjule resultatet etterpå og be om innsyn eller sletting via Kontakt.','rrq-small'));
    play.append(button('Start quizen',async()=>{
      if(consent.checked && !name.value.trim()){status.textContent='Skriv fornavnet ditt eller fjern avkryssingen for topplisten.';name.focus();return;}
      await request('start',{name:name.value,public:consent.checked?'1':'0'}); answers=Array(20).fill(null); index=0; render();
    }));
  }
  function question() {
    clearInterval(tick); play.replaceChildren();
    const bar=el('div',undefined,'rrq-progress'), progress=el('span','Spørsmål '+(index+1)+' av 20'), clock=el('span');
    clock.setAttribute('aria-label','Tid brukt'); bar.append(progress,clock); play.append(bar);
    const updateClock=()=>{const now=serverNow+(performance.now()-syncedAt)/1000;clock.textContent='Tid: '+duration(now-state.started);if(now>=state.ends){clearInterval(tick);status.textContent='En ny quizuke har startet. Last siden på nytt for å spille den nye quizen.';play.querySelectorAll('button,input').forEach(n=>n.disabled=true);}};
    const field=el('fieldset'), legend=el('legend',state.questions[index].q); legend.tabIndex=-1;field.append(legend);
    state.questions[index].a.forEach((a,i)=>{const label=el('label',undefined,'rrq-choice'), radio=el('input');radio.type='radio';radio.name='rrq-answer';radio.value=String(i);radio.checked=answers[index]===i;radio.addEventListener('change',()=>{answers[index]=i;save();});label.append(radio,el('span',a));field.append(label);});
    play.append(field);
    const nav=el('div',undefined,'rrq-nav');
    if(index>0)nav.append(button('Forrige',()=>{index--;question();play.querySelector('legend').focus();},'rrq-secondary'));
    if(index<19)nav.append(button('Neste',()=>{if(answers[index]===null){status.textContent='Velg et svar før du går videre.';return;}index++;question();play.querySelector('legend').focus();}));
    else nav.append(button('Lever svarene',async()=>{const missing=answers.findIndex(a=>a===null);if(missing!==-1){index=missing;question();status.textContent='Svar på spørsmål '+(missing+1)+' før du leverer.';return;}save();await request('submit',{answers:JSON.stringify(answers)});historyLoading=false;render();play.querySelector('h3')?.focus();}));
    play.append(nav,el('p','Klokken går til svarene er mottatt. Du kan gå tilbake og endre svar før du leverer.','rrq-small'));
    updateClock();tick=setInterval(updateClock,1000);
  }
  function result() {
    const heading=el('h3',state.result.score+' av 20 riktige');heading.tabIndex=-1;play.append(heading,el('p','Tid: '+duration(state.result.seconds)+'. Dette er din tellende runde denne uken.'));
    if(state.result.public)play.append(button('Skjul meg fra topplisten',async()=>{await request('hide');historyLoading=false;render();},'rrq-secondary'));
    else play.append(el('p','Resultatet ditt vises ikke på den offentlige topplisten.'));
    const details=el('details'),summary=el('summary','Se fasit og kilder');details.append(summary);
    state.review.forEach((q,i)=>{const box=el('div',undefined,'rrq-review');box.append(el('h4',(i+1)+'. '+q.q),el('p','Ditt svar: '+q.a[state.result.answers[i]]),el('p','Riktig svar: '+q.a[q.correct]),el('p',q.why));const link=el('a','Kilde');link.href=q.url;box.append(link);details.append(box);});play.append(details);
    try{sessionStorage.removeItem(storageKey());}catch(_){}
  }
  request('state').then(render).catch(e=>{status.textContent=e.message;play.append(button('Prøv igjen',async()=>{await request('state');render();}));});
})();
