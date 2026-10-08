/* Uses only WordPress packages: no build service, licence or commercial blocks. */
(function (blocks, element, editor) {
    const el = element.createElement;
    [
        ['match-bar', 'Radio Rubben · Kampbanner', 'Bruker eksisterende valgt eller kommende kamp. Skjules når det ikke er noe å vise.'],
        ['legacy-home', 'Radio Rubben · Eksisterende forside', 'Bevarer dagens forside, nyheter, spilleroversikt og vær. Oppsettet vises på nettstedet. Denne overgangsblokken kan senere erstattes med separate innholdsblokker.'],
        ['player', 'Radio Rubben · Radioavspiller', 'Bruker Radio Rubbens eksisterende strøm og sendestatus. Én avspiller per side.']
    ].forEach(([name, title, description]) => blocks.registerBlockType('radio-rubben/' + name, {
        apiVersion: 3, title, description, category: 'widgets', icon: 'radio',
        supports: { html: false, multiple: false },
        edit: () => el('div', editor.useBlockProps({className: 'rr-editor-module'}), el('strong', null, title), el('p', null, description)),
        save: () => null
    }));
    [
        ['rr-match-day', 'RR · Dagens kamp og avstemning', '[rr_match_day]', 'Viser valgt kamp og Dagens Bremnesing. Bruk én gang på en side.'],
        ['rr-players', 'RR · Bømlo-spillere ute', '[rr_player_matches]', 'Viser spilleroversikten fra eksisterende spillerplugin.']
    ].forEach(([name, title, text, description]) => blocks.registerBlockVariation('core/shortcode', {
        name, title, description, icon: 'groups', attributes: { text }, scope: ['inserter'], isActive: ['text']
    }));
})(window.wp.blocks, window.wp.element, window.wp.blockEditor);
