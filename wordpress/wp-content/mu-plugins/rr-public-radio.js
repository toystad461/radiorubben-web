(() => {
  'use strict';
  if (window.RR_ONE?.streamUrl !== 'https://5.249.252.78/radio.mp3') return;
  const audio = document.getElementById('rr-audio');
  if (audio) {
    const buttons = [...document.querySelectorAll('.rr-play-toggle')];
    const messages = [...document.querySelectorAll('.rr-player-message')];
    let wanted = false, connecting = false, retry = null, deadline = null;
    let generation = 0, failures = 0, lastTime = 0, lastProgress = Date.now();
    function show(message) {
      for (const button of buttons) {
        button.disabled = false;
        button.textContent = wanted ? 'Ⅱ' : '▶';
        button.setAttribute('aria-label', wanted ? 'Pause Radio Rubben' : 'Spill Radio Rubben');
        button.setAttribute('aria-pressed', String(wanted));
      }
      document.body.classList.toggle('rr-is-playing', wanted);
      for (const el of messages) el.textContent = message;
    }
    function cancel() {
      generation++; clearTimeout(retry); clearTimeout(deadline);
      retry = deadline = null; connecting = false;
    }
    function stop(message = 'Avspilling satt på pause.') {
      wanted = false; cancel(); audio.pause(); show(message);
    }
    function schedule() {
      if (!wanted || retry !== null) return;
      cancel();
      const delay = Math.min(3000 * 2 ** Math.min(failures++, 4), 30000);
      show('Forbindelsen er brutt. Kobler til igjen …');
      retry = setTimeout(() => { retry = null; connect(); }, delay);
    }
    async function connect() {
      if (!wanted) return;
      cancel(); connecting = true;
      const attempt = generation;
      lastProgress = Date.now(); lastTime = 0;
      show('Kobler til sendingen …');
      deadline = setTimeout(schedule, 15000);
      try {
        // Always request the live edge, never replay the previous response.
        audio.load();
        await audio.play();
      } catch (error) {
        if (attempt !== generation || !wanted) return;
        if (error?.name === 'NotAllowedError') {
          stop('Nettleseren krever at du trykker Play for å starte lyden igjen.');
        } else schedule();
      }
    }
    for (const button of buttons) button.addEventListener('click', event => {
      // Own playback intent; the theme's handler cannot distinguish retry from Pause.
      event.preventDefault(); event.stopImmediatePropagation();
      if (wanted) stop();
      else { wanted = true; failures = 0; connect(); }
    }, true);
    audio.addEventListener('playing', () => {
      if (!wanted) { audio.pause(); return; }
      clearTimeout(deadline); deadline = null; connecting = false;
      lastProgress = Date.now(); lastTime = audio.currentTime;
      show('Du lytter til Radio Rubben.');
    });
    audio.addEventListener('timeupdate', () => {
      if (audio.currentTime !== lastTime) {
        lastTime = audio.currentTime; lastProgress = Date.now(); failures = 0;
      }
    });
    for (const event of ['error', 'ended']) audio.addEventListener(event, schedule);
    audio.addEventListener('pause', () => {
      if (wanted && !connecting && retry === null && !audio.error && !audio.ended) stop();
    });
    setInterval(() => {
      if (wanted && !connecting && retry === null && Date.now() - lastProgress > 20000) schedule();
    }, 5000);
    window.addEventListener('online', () => { if (wanted && retry !== null) connect(); });
    show('Trykk Play for å høre Radio Rubben.');
  }
  const cards = [...document.querySelectorAll('.rr-radio-card')];
  const minis = [...document.querySelectorAll('.rr-mini-meta strong')];
  const covers = cards.map(card => card.querySelector('.rr-radio-cover img')).filter(Boolean).map(img => {
    const fallback = img.getAttribute('src');
    img.removeAttribute('srcset');
    img.addEventListener('error', () => {
      if (img.getAttribute('src') !== fallback) { img.src = fallback; img.alt = 'Radio Rubben'; }
    });
    return {img, fallback};
  });
  const text = value => typeof value === 'string' ? value.slice(0, 200) : '';
  const label = track => track ? [text(track.artist), text(track.title)].filter(Boolean).join(' – ') : '';
  for (const card of cards) {
    let next = card.querySelector('.rr-small');
    if (!next) { next = document.createElement('p'); next.className = 'rr-small'; card.append(next); }
    next.dataset.rrNext = 'true'; next.textContent = 'Henter sendestatus …';
  }
  function render(data) {
    const stamp = Date.parse(data?.generatedAt);
    const fresh = Number.isFinite(stamp) && Math.abs(Date.now() - stamp) < 30000;
    const live = fresh && data?.schemaVersion === 1 && data.playing === true && data.now;
    const artwork = live && data.now.kind !== 'jingle' && typeof data.now.artworkUrl === 'string'
      && /^https:\/\/5\.249\.252\.78\/radio-status\.php\?cover=[a-f0-9]{64}$/.test(data.now.artworkUrl) ? data.now.artworkUrl : null;
    for (const {img, fallback} of covers) {
      const src = artwork || fallback;
      if (img.getAttribute('src') !== src) img.src = src;
      img.alt = artwork ? 'Cover: ' + label(data.now) : 'Radio Rubben';
    }
    for (const card of cards) {
      const heading = card.querySelector('.rr-radio-track h2');
      const title = card.querySelector('.rr-radio-track div > p:not(.rr-eyebrow)');
      const next = card.querySelector('[data-rr-next]');
      if (heading) heading.textContent = live ? text(data.now.artist) || 'Radio Rubben' : 'Radio Rubben';
      if (title) title.textContent = live ? text(data.now.title) : 'Musikk og jingler fra Radio Rubben';
      if (next) next.textContent = live ? (label(data.next) ? 'Om litt: ' + label(data.next) : '') : 'Sendestatus er ikke tilgjengelig akkurat nå.';
    }
    for (const el of minis) el.textContent = live ? label(data.now) : 'Radio Rubben';
  }
  let pending = false;
  async function refresh() {
    if (pending || document.hidden) return;
    pending = true;
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 6000);
    try {
      const response = await fetch('https://5.249.252.78/radio-status.php', {credentials:'omit',cache:'no-store',signal:controller.signal});
      if (!response.ok) throw new Error('Status unavailable');
      render(await response.json());
    } catch (_) { render(null); }
    finally { clearTimeout(timeout); pending = false; }
  }
  refresh();
  setInterval(refresh, 10000);
  document.addEventListener('visibilitychange', refresh);
})();
