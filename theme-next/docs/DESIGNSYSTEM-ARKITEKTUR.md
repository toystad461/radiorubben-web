# Radio Rubben: gjenbrukbare sidemaler

Gjennomgått 05.10.2026. Kandidat på `design/reusable-page-templates`, stablet på `design/home-three-universes` (PR #53), som bygger på Next PR #28. Dette er et presentasjonsarbeid for staging. Ingen aktivering, datamigrering eller produksjonsdeploy følger med.

## Hva gjennomgangen fant

| Område | Funn og konsekvens |
|---|---|
| `wordpress/wp-content/themes/radio-rubben-wordpress-v1` | Klassisk PHP-theme med egne sidemaler og direkte innlasting av vær, quiz, medlem, kamp og RRLive fra `functions.php`. Et ukontrollert temabytte kan fjerne funksjonalitet. |
| `theme-next/radio-rubben-next` (PR #28) | Hybrid-theme: eksisterende PHP-ruter, `theme.json` v3, syv patterns, komponenter, felles header/footer/minispiller. Ikke et fullstendig block-theme. |
| `theme-next/rr-site-functions` | Migreringsbro eier tidligere theme-motorer og bevarer gamle kontrakter. Passiv med det gamle temaet, konfliktkontroll før oppstart med Next. Ingen ny motor er nødvendig for sidemaler. |
| `theme-next/rr-editorial-contract` | Separat plugin registrerer journalist-/kildemetadata og rettigheter. Theme viser feltene. |
| `RR_Quiz`, `RR_News`, radio.co, Min Rubben og Vipps | Egne plugins eier quiztilstand, RSS/cache, streamintegrasjon, medlemsdata og innlogging. `the_content()` er kompatibilitetsgrensen for eksisterende blokker, filtre og shortcodes. |
| PR #53 | Ny forside og tidsavgrensede live-signaler. Eget opt-in; én eksisterende lydmotor. Brukes som base, slik at felles headerendring ikke utvikles mot en eldre kopi. |
| Maler og CSS | `application.php` manglet innholdspaginering; sidetyper gjentok overskrift/ramme. Editor brukte Inter og blågrå/røde legacyfarger, ulik profilpaletten. Rettet i denne leveransen. |
| Spesialruter | `page-rrlive.php`, `single-rr_match.php`, nyheter og eldre sideslugmaler har særskilt visning. Medlemsside 736 velges av plugin. Disse rutene overstyres ikke automatisk. |

### Åpne PR-er og avhengigheter

Alle 27 åpne PR-er (#28–54) ble listet. Relevant kilde og dokumentasjon ble undersøkt for theme-/funksjonsgrensene:

- [#28 Next](https://github.com/toystad461/radiorubben-web/pull/28): grunnarkitektur, funksjonsbro, historiske stagingtester og fem utestående porter.
- [#53 forside](https://github.com/toystad461/radiorubben-web/pull/53): valgt base, felles navigasjon og avspiller. Samme forsidestruktur og standardvalg beholdes.
- [#35 profil](https://github.com/toystad461/radiorubben-web/pull/35): originale logoer, #FF001B, svart/hvitt, Arial. Next inneholder allerede porteringen. Ingen ny logo tegnes.
- [#32 bortekamp](https://github.com/toystad461/radiorubben-web/pull/32): `opponent` og fire delingstekster; Next-broen kan være eldre. Skal samkjøres før aktivering.
- [#33 quizretur](https://github.com/toystad461/radiorubben-web/pull/33), [#34 deling](https://github.com/toystad461/radiorubben-web/pull/34): funksjonsrettinger eies av quizlaget. Ingen kopi overstyrt her.
- [#45 spillerwidget](https://github.com/toystad461/radiorubben-web/pull/45), [#47 kampoppfølging](https://github.com/toystad461/radiorubben-web/pull/47): egen widget/provider med kildekontroll, cache og refresh; den nyere pluginen må beholdes på staging.
- #29–31 og #36–52: stablede Fotballrobot-/redaksjonsendringer. Publisering, godkjenning, cron og data skal ikke flyttes til theme. PR-tekstene oppgir flere selektive produksjonsutrullinger; åpne PR-er er derfor ikke det samme som upubliserte funksjoner.
- [#54 RSS/Studio](https://github.com/toystad461/radiorubben-web/pull/54): separat release-/transportarbeid, ingen theme-avhengighet som skal importeres her.

Dette er en kode- og PR-gjennomgang, ikke en ny lesing eller attestering av produksjonsserverens aktive filhasher. Ikke erstatt aktiv runtime med hele denne grenen.

## Arkitekturvalg etter WordPress-research

WordPress anbefaler block themes med HTML-maler i `templates/`, deler i `parts/`, og registrerte `customTemplates`/`templateParts` i `theme.json`. Standardblokker og patterns gir redaktøren gjenbruk uten å kopiere PHP-layout. `theme.json` v3 finnes fra WordPress 6.6; vi validerer mot 6.6-skjemaet fremfor trunk, slik at det oppgitte minstekravet er reelt.

Denne leveransen viderefører **hybrid-theme** med PHP-maler, samme navigasjon, customizer og lydkontroller som Next. Et øyeblikkelig full-block-bytte ville også erstattet disse integrasjonene og CPT-/slugmalene. WordPress dokumenterer hybrid som en gradvis overgang. Derfor registreres PHP-maler med `Template Name`, ikke som falske HTML-`customTemplates` i JSON. Det finnes ingen `templates/index.html` eller påstått Site Editor-støtte her.

Neste full-block-steg krever eksplisitt migrering av navigasjon til `core/navigation`, header/footer til `parts/*.html`, layoutene til `templates/*.html` med én `core/post-content`, og særskilt test av minispiller, RRLive, tilgang og lagrede Site Editor-overstyringer. Tokens, vanlige core-blokkpatterns og pluginansvaret kan gjenbrukes. Database-lagrede maloverstyringer må sammenlignes med Git før filendringer forventes synlige.

Kilder lest 05.10.2026:

- [Templates](https://developer.wordpress.org/themes/core-concepts/templates/) og [custom templates](https://developer.wordpress.org/themes/global-settings-and-styles/custom-templates/): filstruktur og begrenset, forståelig malutvalg.
- [theme.json v3](https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/theme-json-living/) og [layout settings](https://developer.wordpress.org/themes/global-settings-and-styles/settings/layout/): felles presets og innholdsbredder.
- [Registering patterns](https://developer.wordpress.org/themes/patterns/registering-patterns/): automatiske PHP-patternfiler, core-blokker og redigerbart startinnhold.
- [Hybrid themes](https://developer.wordpress.org/news/2024/12/bridging-the-gap-hybrid-themes/): gradvis overgang uten å erstatte hele eksisterende templatesystemet.
- [Theme functions](https://developer.wordpress.org/themes/classic-themes/basics/theme-functions/): varig funksjonalitet hører hjemme i plugins.

## Fire maler for nye sider

| Valg i sideeditoren | Fil | Bruk |
|---|---|---|
| RR · Standard innholdsside | `templates/content.php` | Vanlig informasjon, om-sider og korte kombinasjoner; 900 px maksimal ramme. |
| RR · Bred app / dashboard | `templates/application.php` | Flere funksjoner på én skjerm; 1440 px ramme. Samme filnavn som tidligere applikasjonsmal, så eksisterende tilordninger beholdes. |
| RR · Redaksjonell / artikkel | `templates/editorial.php` | Sider og innlegg med byline, bilde/kreditering, kilder og journalist. Deler `template-parts/content/article.php` med `single.php`; 720 px lesebredde. Innleggsnavigasjon vises bare på innlegg. |
| RR · Fotball / live | `templates/football.php` | Kamp, stemmemodul og spilleroversikt i samlet sideinnhold; 1180 px ramme. Setter aldri kampstatus eller tilgang. |

De tre innholdsmalene deler `template-parts/page/layout.php`, `heading.php` og `sections.php`. Én hovedløkke, én H1 og ett `the_content()`-kall; WordPress beholder passordskjema, shortcodes, filtre og paginering. Header, footer og minispiller er de eksisterende site-delene; hovednavigasjon er skilt ut som `template-parts/site/navigation.php` uten at menyatferden endres.

Gamle spesialmaler beholdes av kompatibilitetshensyn. Standardinnholdsmalen er et eksplisitt valg; `page.php` og eksisterende sidetildelinger endres ikke automatisk. Dette begrenser den nye redaksjonelle arbeidsflyten til fire maler uten å slette legacyvalg.

## Patterns, kort og seksjoner

- `section.php`: vanlig seksjon med H2 og plass til innhold.
- `cards.php`: to gjenbrukbare innholdskort.
- `combined-football.php`: Dagens kamp først, deretter Dagens Bremnesing og Bømlo-spillere ute.
- `combined-dashboard.php`: hovedoversikt, vær/informasjon og eksisterende `[rr_weekly_competition]`.

Patterns er startinnhold, ikke levende synkroniserte maler. Hjelpetekst skal erstattes med eksisterende pluginblokk eller verifisert kortkode før publisering. Kamp og vær har ikke et universelt blokk-/kortkode-API i den gjennomgåtte broen; vi oppfinner ikke kortkoder eller flytter PHP-motorer inn i theme. En pluginadapter trengs dersom en eksisterende funksjon kun finnes som full siderute. RRLive-ruter og medlemsruter beholdes til slike adaptere er testet. Nyheter kan settes inn med WordPress Query Loop eller eksisterende RSS-plugin; publiserte artikler bruker vanlig WordPress-spørring.

Slik bygger redaktøren en ny side på staging:

1. Velg én av de fire RR-malene.
2. Sett inn et Radio Rubben-mønster fra blokkvelgeren.
3. Bytt hjelpeteksten med innhold og eksisterende modulblokk/kortkode. Kontroller at eierplugin er aktiv; ikke sett samme quiz-/stemmeinstans inn to ganger.
4. Gi H2-overskriftene unike HTML-ankere (ASCII-bokstaver, tall, bindestrek/understrek). Ved minst to ankere bygges «På denne siden» automatisk. Kopierte patterns krever nye ankere.
5. Test utlogget/innlogget og mobil. Ikke publiser hjelpetekst eller rå kortkoder.

Ankerlisten leser core heading/group/columns/column uten å kjøre dynamiske blokker. Den følger innholdsrekkefølgen, krever ingen JavaScript og vises ikke for passordbeskyttet eller flersidig innhold. Synkroniserte mønstre og plugin-genererte overskrifter indekseres ikke automatisk.

## Designregler

`theme.json` er kilde for nye farger, typografi, avstander og bredder. Profilens eksisterende SVG-filer brukes urørt. Nye CSS-primitiver bruker presets med profilriktige fallbacks, også i blokkeditoren. Legacy CSS/profiloverstyringer beholdes; dette er ikke en risikabel total opprydding av gamle selektorer.

- Mobil er én kolonne. To kolonner innføres fra 760 px, med `minmax(0,…)`, samme DOM-rekkefølge og ingen tvungen sidehøyde.
- Hovedfunksjon først, sekundærinformasjon deretter. Seksjonslenker reduserer sidebytter; nye sider får ingen ekstra skjerm-/fanemotor.
- Avstandsskala: 8/16/24/32/48/64 px. Rammer: innhold 900, bred 1180, app 1440; langtekst 720 px.
- Lenker i seksjonsmenyen har minst 44 px høyde, synlig fokus og kan brukes med tastatur.
- Svart knappetekst på profilrødt beholdes. Semantiske overskrifter, alt-tekst/bildekreditering og reduced-motion fra eksisterende theme videreføres.
- Ikke style interne plugin-ID-er eller overstyr kontroll-/live-/stemmetilstand med CSS. Brede tabeller ruller i sin lokale blokk; ikke skjul hele sidens overflyt.

## Theme mot plugin

| Theme eier | Plugin/WordPress eier |
|---|---|
| Tokens, typografi, layout, kort, header/footer, meny og in-page navigasjon | API-kall, cache, cron, CPT/REST, innlogging, rettigheter/nonces |
| Escapet lesepresentasjon av eksisterende data | Quiz, stemmer, kampklokke, vinner, medlemsdata, betaling, sletting |
| `the_content()` og komponentkontrakter | Dataregistrering, robot/AI, publisering, e-post, redaksjonell godkjenning |

Nye komponenter skal ikke registrere endepunkter, gjøre nettverkskall eller lagre data. Utvid plugins med renderkontrakt/blokk hvis noe skal kunne komponeres. CSS-skjuling er aldri tilgangskontroll. Temaet skal ikke tolke åpen avstemning som bekreftet livekamp; eksisterende legacy-adaptere må fortsatt revideres separat før produksjonsbytte.

## Verifisering og staging-gates

Automatisert: PHP/JS/JSON-kildekontroll og presentasjonsgrense, 51 nye kontroller av faktisk malgjengivelse med isolerte WordPress-stubber og ekte core-blokkparser, 38 eksisterende forsidekontroller, 16 nettleservisninger (fire maler × 320/390/768/1440), skjemavalidering mot WordPress 6.6, eksisterende kilde-/byggtester, lesetestregresjoner og ZIP-bygg. Preview bruker eksempelinnhold; pluginrespons er simulert. Den beviser ikke WordPress-editorlagring eller ekte API/Vipps/quizflyt.

Før aktivering på produksjon skal ansvarlig teste en isolert stagingkopi med **ferske** plugins/innhold og runtime-hasher:

1. Samkjør PR #32/#33/#34 og aktive spiller-/robotrettinger i pluginlaget; behold eksisterende data og kontrollér alle korte koder/ruter. Ingen bred deploy fra denne grenen.
2. Velg/lagre hver ny mal i WordPress, sett inn og lagre patterns uten blokkvalideringsfeil, og kontroller editor/front-end, spesialslugger, barnetheme og eventuelle lagrede overstyringer.
3. Test ekte RRLive/kamp, Dagens Bremnesing, spillerwidget, vær, RSS og quiz med utlogget, medlem og redaktør; empty/loading/error/expired-tilstander, cache og plugin-CSS ved 320 px og 200 % zoom.
4. Fullfør PR #28s fem porter: Vipps-retur, faktiske import/cache-visninger, hørbar lyd med bare én lydmotor, utsending/automasjon under godkjent testopplegg, og drift/cache/backup/tilbakeføring.
5. Fjern pattern-hjelpetekst og sørg for unike ankere. Godkjenn design og faktisk brukerflyt før separat aktiveringsbeslutning.

Ny workflow har kun lesetilgang og ingen deploy-jobb eller produksjonshemmeligheter. Gamle stagingresultater er ikke presentert som nye produksjonsbevis.
