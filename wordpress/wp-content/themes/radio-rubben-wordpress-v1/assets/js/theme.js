(() => {
  const audio = document.getElementById('rr-audio');
  const toggles = document.querySelectorAll('.rr-play-toggle');
  const messages = document.querySelectorAll('.rr-player-message');
  const volume = document.getElementById('rr-volume');
  const configured = !!(window.RR_ONE && RR_ONE.streamUrl);
  let pending = false;
  const status = text => messages.forEach(el => { el.textContent = text; });
  function sync() {
    const playing = !!(audio && !audio.paused && !audio.error);
    toggles.forEach(btn => {
      btn.textContent = pending ? '…' : playing ? 'Ⅱ' : '▶';
      btn.disabled = !configured || pending;
      btn.setAttribute('aria-label', playing ? 'Pause Radio Rubben' : 'Spill Radio Rubben');
      btn.setAttribute('aria-pressed', String(playing));
    });
    document.body.classList.toggle('rr-is-playing', playing);
  }
  if (audio) {
    if (configured) audio.src = RR_ONE.streamUrl;
    audio.volume = volume ? Number(volume.value) : 0.8;
    audio.addEventListener('playing', () => { status('Du lytter til Radio Rubben.'); sync(); });
    audio.addEventListener('pause', () => { status('Avspilling satt på pause.'); sync(); });
    audio.addEventListener('waiting', () => status('Kobler til sendingen …'));
    audio.addEventListener('error', () => { pending = false; status('Vi får ikke kontakt med sendingen. Trykk play for å prøve igjen.'); sync(); });
    audio.addEventListener('ended', () => { status('Sendingen er avsluttet.'); sync(); });
  }
  toggles.forEach(btn => btn.addEventListener('click', async () => {
    if (!configured || !audio || pending) return;
    if (!audio.paused) { audio.pause(); return; }
    pending = true; sync(); status('Kobler til sendingen …');
    // Bound connection attempts so an unavailable stream cannot lock the controls.
    const timeout = setTimeout(() => { if (pending) { audio.pause(); pending = false; status('Tilkoblingen tok for lang tid. Prøv igjen.'); sync(); } }, 15000);
    try {
      if (audio.error) audio.load();
      await audio.play();
    } catch (_) { status('Kunne ikke starte lyden. Prøv igjen.'); }
    finally { clearTimeout(timeout); pending = false; sync(); }
  }));
  if (volume && audio) volume.addEventListener('input', () => { audio.volume = Number(volume.value); });
  sync();
  const menuBtn = document.querySelector('.rr-menu-toggle');
  const nav = document.querySelector('.rr-nav');
  if (menuBtn && nav) {
    const close = () => { nav.classList.remove('is-open'); menuBtn.classList.remove('is-open'); menuBtn.setAttribute('aria-expanded', 'false'); };
    menuBtn.addEventListener('click', () => { const open = nav.classList.toggle('is-open'); menuBtn.classList.toggle('is-open', open); menuBtn.setAttribute('aria-expanded', String(open)); });
    nav.querySelectorAll('a').forEach(a => a.addEventListener('click', close));
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && nav.classList.contains('is-open')) { close(); menuBtn.focus(); } });
  }
})();
