(() => {
  'use strict';
  const init = () => {
    if (!document.body.classList.contains('page-id-736')) return;
    const loggedIn = document.body.classList.contains('logged-in');
    if (!loggedIn) {
      const entry = document.querySelector('.rr-vipps-entry');
      const challenge = document.querySelector('.rr-quiz-challenge');
      if (entry && challenge) entry.after(challenge);
    }
    const dashboard = document.querySelector('.rr-member-dashboard:not(.rr-member-support)');
    const shortcuts = dashboard?.querySelector('.rr-member-shortcuts');
    const grid = dashboard?.querySelector('.rr-member-grid');
    if (shortcuts && grid) dashboard.insertBefore(shortcuts, grid);
    function fold(section, label, id) {
      if (!section || section.parentElement?.classList.contains('rr-member-fold')) return null;
      const details = document.createElement('details');
      details.className = 'rr-member-fold';
      if (id) details.id = id;
      const summary = document.createElement('summary');
      summary.textContent = label;
      section.before(details);
      details.append(summary, section);
      const title = section.querySelector(':scope > h3, :scope > h2');
      if (title) title.hidden = true;
      return details;
    }
    fold(dashboard?.querySelector('.rr-member-badges'), 'Dine quizmerker');
    const root = document.querySelector('.rr-member-columns > .wp-block-column .rrm');
    if (root && loggedIn) {
      root.classList.add('rr-member-tools');
      const cards = Array.from(root.querySelectorAll(':scope > section.rrm-card'));
      const send = cards.find(card => card.querySelector('input[name="action"][value="rrm_submit"]'));
      const sending = fold(send, 'Ønsk en låt eller send en hilsen', 'rr-member-message');
      cards.filter(card => card !== send).forEach(card => {
        fold(card, card.querySelector('h3')?.textContent || 'Mine innsendinger');
      });
      fold(root.querySelector(':scope > .rrm-rules'), 'God tone på Rubben');
      const editName = root.querySelector(':scope > details.rrm-card');
      const profile = document.querySelector('#rr-my-information');
      if (editName && profile) {
        editName.classList.add('rr-member-edit-name');
        profile.after(editName);
      }
      const kind = new URLSearchParams(location.search).get('rrm_kind');
      if (sending && (kind === 'wish' || kind === 'greeting')) {
        sending.open = true;
        const select = send.querySelector('select[name="kind"]');
        if (select) select.value = kind;
        requestAnimationFrame(() => sending.scrollIntoView({block:'start'}));
      }
      // Keep validation feedback visible even when the form was collapsed.
      send?.addEventListener('invalid', () => { if (sending) sending.open = true; }, true);
    }
    document.body.classList.add('rr-member-tidy-ready');
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
