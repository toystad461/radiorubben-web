(() => {
  'use strict';
  function update() {
    const now = Date.now() / 1000;
    document.querySelectorAll('.rrpw-mini').forEach(tile => {
      const status=tile.querySelector('.rrpw-mini-status');
      if (Number(tile.dataset.cardExpires)>0 && Number(tile.dataset.cardExpires)<=now) {
        status.textContent=''; status.hidden=true; status.classList.remove('is-confirmed');
        tile.querySelector('.rrpw-mini-fixture').replaceChildren(); tile.dataset.kickoff='0';
        tile.querySelector('.rrpw-mini-links')?.remove();
      } else if (status.classList.contains('is-confirmed') && Number(status.dataset.lineupExpires)<=now) {
        status.textContent=''; status.hidden=true; status.classList.remove('is-confirmed');
      }
      const media=tile.querySelector('.rrpw-mini-media');
      if (media && [...media.querySelectorAll('[data-expires]')].some(a=>Number(a.dataset.expires)<=now)) {
        media.replaceChildren();const label=document.createElement('span');label.className='rrpw-mini-unknown';label.textContent='Sending uavklart';media.append(label);
      }
      tile.querySelectorAll('.rrpw-portrait img').forEach(img=>{
        const fallback=()=>{img.hidden=true;img.parentElement.querySelector('svg').removeAttribute('hidden');};
        if(img.complete&&!img.naturalWidth)fallback();else img.addEventListener('error',fallback,{once:true});
      });
      tile.querySelectorAll('.rrpw-mini-club img').forEach(img=>{
        if(img.complete&&!img.naturalWidth)img.hidden=true;
        else img.addEventListener('error',()=>{img.hidden=true;},{once:true});
      });
    });
    document.querySelectorAll('.rrpw-strip').forEach(strip=>{
      const tiles=[...strip.querySelectorAll('.rrpw-mini')];
      const sorted=[...tiles].sort((a,b)=>(Number(a.dataset.kickoff)||Infinity)-(Number(b.dataset.kickoff)||Infinity));
      if(sorted.some((tile,i)=>tile!==tiles[i]))sorted.forEach(tile=>strip.append(tile));
    });
    document.querySelectorAll('.rrpw').forEach(card => {
      if (Number(card.dataset.cardExpires) <= now) {
        const notice = document.createElement('div');
        notice.className = 'rrpw-empty';
        notice.textContent = 'Kampdata må oppdateres. Last siden på nytt for ny status.';
        card.replaceWith(notice);
        return;
      }
      if (Number(card.dataset.start) <= now) card.querySelector('.rrpw-badge').textContent = 'Kampstart passert';
      card.querySelectorAll('a[data-expires]').forEach(link => {
        if (Number(link.dataset.expires) <= now) {
          const text = document.createElement('p');
          text.className = 'rrpw-unconfirmed'; text.textContent = 'Sending må bekreftes på nytt';
          link.replaceWith(text);
          card.querySelector('.rrpw-subscription')?.remove();
        }
      });
      card.querySelectorAll('[data-lineup-expires]').forEach(label => {
        if (Number(label.dataset.lineupExpires) <= now) label.textContent = 'Tropp ikke bekreftet';
      });
      card.querySelectorAll('.rrpw-crest img').forEach(img => {
        const fallback = () => { img.hidden = true; img.parentElement.querySelector('.rrpw-initial').hidden = false; };
        if (img.complete && !img.naturalWidth) fallback();
        else img.addEventListener('error', fallback, {once:true});
      });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', update, {once:true});
  else update();
  window.addEventListener('pageshow', update);
  setInterval(update, 30000);
})();
