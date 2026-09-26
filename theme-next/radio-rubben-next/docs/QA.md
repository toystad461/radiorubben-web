# Verifisering – 26. september 2026

## Faktisk utført

- **70 PHP-filer** i theme og metadata-plugin: syntakskontroll bestått med PHP 8.4.25.
- `theme.json`: validert mot WordPress 6.6 sitt offisielle v3 JSON Schema.
- `assets/js/ui.js`: JavaScript-syntaks kontrollert med Node. Ingen console-feil/advarsler observert i de inspiserte sidene.
- Tema-, metadata-plugin- og child-theme-ZIP installert gjennom ekte WordPress-installasjonsflyt i **isolert lokal WordPress 7.1.2** med SQLite. Ingen produksjonsdatabase kopiert eller endret.
- **54 integrasjonskontroller bestått**: temaegenskaper, mal-/mønsterregistrering, identiteter, menneskelig fallback, gammel robotmarkør, ukjent identitet, komponent-allowlist, XSS/URL-escaping, korrekt 0–0, manglende resultater, ufullstendige RRLive-data, REST-skriverettigheter for anonym/annen forfatter/egen forfatter, sanitization, vedvarende metadata, utkaststatus og at temabytte ikke endrer innlegg.
- **34 lokale sider/ruter bestått**, inkl. arkiv side 2, hele kategorihierarkiet, søk med/uten treff og skadelig søketekst, standard-/robotartikkel, passordbeskyttet innlegg, journalistprofiler, program/markedsføringssider, applikasjonsrammer, RRLive med manglende data, array-parametre og reell HTTP 404. Ett main/h1, ingen doble ID-er eller lekket passordinnhold i disse svarene. Ni lokale CSS/JS-ressurser svarer 200 i parent-testen.
- Child theme installert/aktivert: foreldrekode og innstillinger fungerer; samme 34 ruter besto (ti lokale CSS/JS-filer pga. child-stil).
- Eksisterende **Fotballrobot 0.3.2** kopiert uendret til lokal testinstallasjon. Dens `the_content`-filter, faktakort, tidslinje og den nye bylinen vises sammen på testartikkel med fixture-data. Ingen AI-/NFF-kall eller nye produksjonsutkast.
- Uten metadata-plugin, robot og testdataleverandør: forside, artikkel, RRLive-fallback, sport og kontakt svarer 200; byline fra lagret metadata består; temaet oppretter ikke `rr_match`.
- Visuell inspeksjon ved **320, 390, 768, 1200 og 1440 px**. Ingen horisontal dokumentoverflow i de inspiserte kombinasjonene. Mobilmeny åpner/lukker, oppdaterer `aria-expanded`, og Escape returnerer fokus til menyknappen. Radio er deaktivert når strøm mangler. Profil-/kampkort og fotballrobotens faktakort inspisert på mobil.
- Temaets PHP-logg: ingen registrerte feil/advarsler fra ny theme eller metadata-plugin under testene. WordPress sine egne eksterne oppdateringssjekker ga tilkoblingsvarsler fordi testmiljøet bevisst blokkerer ekstern HTTP; dette er ikke talt som en bestått nettverksintegrasjon.
- Statisk grensekontroll: ingen CPT-/REST-registrering, fjerninnhenting, cron, innleggsskriving eller option-skriving i temaets PHP-filer.
- ZIP-struktur, filinnhold og SHA-256 kontrollert. Kun egne theme-/plugin-/child-filer inngår; ikke database, testmiljø, private nøkler eller gammel applikasjonskode.

## WordPress Theme Check

Theme Check **20260901**: 0 REQUIRED-feil og 0 WARNING.

Tre anbefalinger gjenstår med hensikt: `custom-header`, `custom-background` og widget-sidebars. Layouten bruker eget logo-/token-/mønstersystem, og widgets flyttes ikke automatisk. Hvis en produksjonsflate faktisk er avhengig av widgets, må den kartlegges på staging. To INFO-meldinger gjelder korrekt text-domain og faste kilde-/lisenslenker for Mosterhamn-bildet; disse lenkene skal beholdes som kreditering.

Dette er ikke en full WordPress.org Theme Review eller en full sikkerhets-/WCAG-sertifisering. Ingen PHPCS/WPCS- eller PHPCompatibility-analyse er påstått.

## Begrensninger før produksjon

- Runtime på produksjonens PHP **8.2.34**, MySQL, faktiske cache-/SEO-/Vipps-/RSS-/radio-/medlemsplugins og full produksjonsdata er ikke ende-til-ende-testet. Lokal runtime var PHP 8.4.25 / SQLite.
- WordPress 6.6 er angitt minsteversjon for v3 tokens og API-bruk; den konkrete minimumsversjonen er ikke runtime-testet her.
- Nyeste komplette kilde til theme-bundne kamp-/medlems-/quizmotorer mangler lokalt. Siste komplette kodebackup er fra 23. september. Flytting av disse motorene er **ikke implementert eller publisert** i denne pakken.
- Eksisterende URL-er endres ikke av temaet. Tilgjengeligheten til spesialruter avhenger fortsatt av motorene; ikke godkjent for produksjonsbytte før migreringslisten i DEPLOY er lukket.
- RSS/kamp i lokale skjermbilder er testleverandører. Skjermbilder viser ikke en full produksjonskopi eller fungerende avstemning/dashboard. Værmotoren er ikke aktiv i den lokale forhåndsvisningen.
- Reell lydavspilling, Vipps-flyt, avstemning, kampadministrasjon, skjema-utsending, cacheoppførsel og ytelsesmåling med ekte produksjonstrafikk gjenstår på staging. Ingen slike handlinger er kjørt på produksjon.
- Markedsføringsmaler er videreført fra kildekopien. Juridiske sider bruker lagret sideinnhold; sammenlign med dagens renderte sider før bytte.

Testlogger, automatiske tester og skjermbilder finnes i leveransens lokale `qa/`-mappe, utenfor installasjons-ZIP-en.
