(() => {
  'use strict';
  function update() {
    const now = Date.now() / 1000;
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
