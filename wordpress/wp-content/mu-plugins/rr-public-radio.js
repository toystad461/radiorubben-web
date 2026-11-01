(() => {
  'use strict';
  if (window.RR_ONE?.streamUrl !== 'https://5.249.252.78/radio.mp3') return;
  const cards = [...document.querySelectorAll('.rr-radio-card')];
  const minis = [...document.querySelectorAll('.rr-mini-meta strong')];
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
