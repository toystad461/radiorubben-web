# Migrering og staging – 26. september 2026

## Status for Thomas

**Funksjonene fra dagens theme er sikret i en separat, installérbar utvidelse og prøvd sammen med Next på en isolert stagingkopi. Produksjon er ikke byttet.**

Ny pakke: `rr-site-functions-1.0.0-rc.3.zip`. Hovedthemet er fortsatt `radio-rubben-next-2.0.0-rc.1.zip`. De skal vurderes sammen. Metadata-pluginet og child theme er egne valg.

Dette statusnotatet oppdaterer migreringsstatusen i den opprinnelige theme-kandidatens `docs/DEPLOY.md`: fersk kildekopi, uttrekk av funksjoner og lokal staging er nå gjennomført. De opprinnelige theme-dokumentene beskriver også situasjonen før denne funksjonsutvidelsen ble laget.

## Grunnlag og privat backup

Med uttrykkelig tillatelse ble database og WordPress-filer hentet via lesetilgang. Kopien omfatter wp-content (themes, plugins, opplastinger), wp-config.php og .htaccess, uten cache-/backup-/oppgraderingsmapper. Databaseeksporten var omtrent 28 MB og inneholdt 41 tabeller. WordPress-kjerne 7.1.2 ble satt opp separat på staging. Original konfigurasjon er bare oppbevart i den private backupen; staging bruker egen database, passord og salter.

Arkivet kunne pakkes ut. Alle filene i det aktive themet samsvarte med SHA-256 fra produksjon. Ni filer var nyere/annerledes enn den eldre kildekopien; migreringen bruker den ferske versjonen. Backupfiler, database, innlogginger og medlemsdata er ikke lagt i GitHub eller installasjons-ZIP.

Filkopieringen ga varsel om at selve wp-content-katalogen endret seg mens nettstedet var i drift. Arkivet var lesbart og theme-hashene stemte, men dette er ikke en atomisk backup av hele nettstedet på ett tidspunkt. Databasekopien ble gjenopprettet og brukt i testene. Ta en ny koordinert backup rett før et senere produksjonsbytte; ikke bruk denne eldre stagingdatabasen til å overskrive nyere aktivitet.

## Isolering

- Lokal adresse: `http://127.0.0.1:8877/`; ikke en offentlig stagingtjeneste.
- WordPress 7.1.2, PHP **8.2.34**, MariaDB 11.4. Databaseplattformen er ikke dokumentert identisk med produksjonens MySQL-konfigurasjon.
- WordPress og database kjører på internt Docker-nett uten utgående internett. En separat lokal mellomtjener gir nettlesertilgang.
- Utgående WordPress HTTP, e-post, cron og automatiske oppdateringer er blokkert. Direkte nettverksforsøk fra WordPress-containeren ble også avvist.
- Nettleserpolicy blokkerer eksterne skript, bilder, strømmer, skjemaer og tilkoblinger. Kjente laglogoer leveres nå lokalt fra Site Functions rc.2; andre eksterne bilder forblir blokkert.
- Staging har noindex. Private kopier ligger utenfor repositoryet i en mappe med begrensede filrettigheter.

## Implementert migrering

| Område | Gjennomført |
|---|---|
| Kamp og Dagens Bremnesing | Ferske motorfiler flyttet til plugin-stier; eksisterende lagringsnøkler og handlers beholdt. |
| Speaker og dashboard | Samme tilgangskontroll, nonce og kampmotor. Alle dashboard-rutene beholdt. |
| Dashboard-test | Aktiv Code Snippets #9 hadde direkte avhengighet til gammel theme-fil. En flyttet handler i pluginet overtar før originalen når Next er aktivt. |
| Kamparkiv | Eksisterende arkiv og resultatvisning beholdt. Den lagrede Bremnes–Viggo-kampen ble visuelt inspisert. |
| RRLive | Registrering og datafunksjoner flyttet ut av theme. Eksisterende `/rrlive/kamp/`-struktur beholdt. |
| Medlemsfunksjoner | Profil-/konto-/støttemaler og sletting flyttet med, slik at de ikke faller ut når vanlig sidemal endres. |
| Quiz og vær | Kontroller, handlers, personvernverktøy og egne CSS/JS flyttet med. |
| RSS | Ny forside bruker eksisterende RR_News-renderer/cache når ingen annen feedleverandør er konfigurert. Ingen ekstra RSS-jobb. |
| Tilbakeføring | Funksjonsutvidelsen er passiv med gammelt theme, også ved tilbakebytte. |
| Presentasjon | Innvendige app-`main`-elementer endret til seksjoner, fordi Next allerede har sidens main-element. Dashboard-prototypens velger er tilpasset dette. |

## Faktiske resultater

**40 av 40 HTTP-handlingstester besto**, med særskilte syntetiske kontoer og kamp-ID 99999991. De omfattet:

- lokal administrator- og medlemsinnlogging, dashboard og dashboard-prototype;
- avvist styring uten innlogging, med vanlig medlemsrolle og med ugyldig nonce;
- ny testkamp, første omgang, mål, bytte, pause og andre omgang;
- kumulativ spillerliste: både utbyttet starter og innbytter er fortsatt stemmeberettiget;
- avvist anonym stemme og ugyldig stemmetoken, akseptert teststemme og avvist dobbeltstemme;
- stenging ved 85:00 og at korrigering bakover ikke åpner avstemningen igjen;
- avslutning, arkivering og offentlig visning uten private trekningsdetaljer;
- syntetisk RRLive-kamp på den eksisterende permalinkstrukturen;
- quizstart med nonce, 20 spørsmål uten fasit under spill, poengberegning og at ny innsending ikke overskriver resultat;
- medlemsprofil og sletting av en syntetisk konto med korrekt bekreftelse; feil bekreftelse ble avvist.

**37 av 37 funksjons-/isoleringskontroller besto:** funksjonseierskap, kortkoder, RRLive, AJAX-handlers, radioinnstilling, LIVE-quizens av/på-sperre, personverneksport/-sletting, deaktivert bursdagsinnsamling, e-post og nettverk.

Ved selve theme-bytte var hashene for artikkel-/sideinnhold og applikasjonsinnstillinger uendret. Brukertall, kortkoder og RRLive-registrering var også bevart. Broen var passiv med gammelt theme og aktiv med Next. Tilbakebytte og nytt bytte ble gjennomført på staging.

**18 ruter ble sammenlignet før/etter**, med identisk HTTP-status og uten serverfeil i den endelige kjøringen. **36 lokale CSS/JS-ressurser** svarte uten feil. Beskyttede dashboard-adresser sendte anonyme til innlogging. `/rrlive/` svarte 404 med begge themes fordi en publisert oversiktsside mangler i kopien; dette ble ikke skjult ved å opprette en ny side. En konkret RRLive-kamp ble testet separat og svarte 200.

**Visuell kontroll:** forside, kamp-/arkivside og dashboard-prototype ble inspisert på mobil; dashboard også ved 1440 px. De målte 390/1440-visningene hadde ikke horisontal dokumentoverflow. Dashboard-prototypen viste testresultat og hendelser, og hadde ingen registrerte JavaScript-feil i den inspiserte nettlesertilstanden. Dette er ingen full WCAG- eller ytelsesrevisjon.

**Statisk kontroll:** 104 distribuerte PHP-filer, JavaScript-syntaks, JSON, pakkegrenser og ZIP-bygg besto før endelig pakking. Alle theme-motorene ligger i pluginet. Den ferdige ZIP-en ble installert gjennom WordPress sin faktiske utvidelsesinstaller; alle 37 kontraktkontrollene besto også på den installerte pakken. Syntetiske kontoer/kamper ble ryddet etter testene og de relevante lokale innstillingene gjenopprettet.

## Funn under test og hvordan de ble håndtert

- Dashboard-testens skjulte theme-avhengighet ble faktisk flyttet og testet.
- Vipps krever ekstra bekreftelse for medlemsrollen i dagens oppsett. Kun nyopprettede, disponible testkontoer fikk et lokalt unntak. Eksisterende brukeres innstillinger og produksjonsinnlogging ble ikke endret.
- Quizens innebygde ukepakke har prioritet over lagret testpakke. Testen ble rettet til å kontrollere den pakken motoren faktisk bruker; poengberegningen besto.
- Kirki forventet filrettighetskonstanter som manglet i lokal konfigurasjon. De ble satt i staging-config og baseline ble kjørt på nytt. Dette var ikke en theme-migreringsendring på produksjon.
- ThemeZee Toolkit gir en eksisterende melding om tidlig lasting av oversettelser. Den er ikke regnet som en ny feil fra migreringsutvidelsen. Ingen PHP-feil fra Next/migreringsutvidelsen ble registrert under de endelige testene.

## Dette er fortsatt ikke godkjent som ferdig produksjonsbytte

1. **Ekte Vipps-innlogging og retur:** må prøves i et godkjent eksternt testoppsett med passende returadresse. Den isolerte testen bekrefter funksjonene etter lokal innlogging, ikke Vipps sin eksterne bekreftelse.
2. **Nye eksterne data:** WordPress-integrasjonene er fortsatt sperret av stagingnettet. Oppfølgingen 27.09 nedenfor dokumenterer separate lesekontroller av kildene; disse erstatter ikke fersk import, cache og visning gjennom kandidaten (gate G2).
3. **Lydavspilling:** kopiens strøm-innstilling er bevart. Nettsiden viser sendingene som pauset; det er ikke dokumentert en vellykket avspilling av en aktiv strøm i denne testen.
4. **Utsending og automasjon:** ekte e-post, skjema-utsending, eventuelle betalingskoblinger, cron og tredjepartsautomatisering er ikke kjørt. Det var tilsiktet for å unngå sideeffekter fra kopien.
5. **Endelig driftssjekk:** reell cache/CDN, ytelse, serverkonfigurasjon, ny backup og avtalt byttetidspunkt må kontrolleres. MariaDB-staging erstatter ikke dette.

Det er derfor fortsatt en **stagingkandidat**, men funksjonsflyttingen og den lokale overgangstesten er nå utført. Et produksjonsbytte er et eget steg.

## Trygg rekkefølge ved senere bytte

1. Kontroller ferske produksjonshasher mot migreringsgrunnlaget; hent nye endringer hvis koden har flyttet seg videre.
2. Fullfør sluttkontrollene ovenfor med passende testintegrasjoner, og få utseendet godkjent.
3. Ta ny backup og avtal et rolig tidspunkt uten pågående kamp/avstemning.
4. Installer og aktiver `rr-site-functions` mens gammelt theme fortsatt er aktivt; kontroller passiv tilstand.
5. Installer hovedtheme og bytt etter avtalt plan. Behold eksisterende plugins/snippets/data og WordPress-salter. Ingen database fra staging skal kopieres over produksjon.
6. Kontroller funksjoner og cache. Ved feil: gå tilbake til gammel theme-/plugin-kombinasjon; broen blir passiv. Ikke gjenopprett gammel database uten særskilt vurdering av nye data.

Ingen av disse produksjonstrinnene er utført i denne oppgaven.

Maskinlesbar, dataminimert testoppsummering: [migration-2026-09-26.json](../tests/results/migration-2026-09-26.json). Ingen testpassord, medlemsopplysninger eller backupinnhold inngår.

Ved ZIP-installasjonen meldte eksisterende Starter Templates om en gammel absolutt loggsti i stagingkopien. WordPress sine oppdateringssjekker ble blokkert av nettverkssperren. Disse miljø-/tredjepartsvarslene er ikke skjult eller regnet som vellykkede eksterne integrasjonstester.

## Logooppdatering – Site Functions 1.0.0-rc.2

Logoene på `/dagenskamp/` pekte til images.fotball.no og ble blokkert av stagingens bildepolicy. Tolv klubblogoer fra Fotballdatas logobank er nå pakket med funksjonspluginet. Visningslaget oversetter kjente FIKS-logo-URL-er til lokale filer, også for arkiverte kamper, uten å skrive til kampdataene. Ingen nye nettverkskall utføres ved sidevisning. Klubb-ID-ene stammer fra eksisterende kampdata og terminliste. Ukjente klubber trenger fortsatt en lokal logo lagt til.

Verifisert 26.09.2026: alle 108 PHP-filer, JavaScript, JSON og pakkekontroller bestått; installert rc.2-ZIP på staging. Dagens kamp (Bremnes–Arna-Bjørnar 2), arkivkampen (Bremnes–Viggo) og `/nestekamp/` leverer begge logoer lokalt med HTTP 200 og gyldige JPEG-filer. Nettleseren bekreftet at dagens to logoer var lastet (200 px bildebredde), og visningen ble kontrollert visuelt. Produksjon og stagingens nettverksvern er uendret.

## Kampklokke – Site Functions 1.0.0-rc.3

Den aktive, gamle Code Snippets-layouten skjulte `.poll-timer` med `display:none!important`. Pluginets offentlige kampkort har nå en avgrenset layoutregel som viser klokken mellom lagene, også når dette snippetet er aktivt. Kampmotor, start/stopp og lagrede tider er uendret.

Kontrollert i nettleser på staging: synlig nedtelling som oppdateres, begge logoer, mobilbredde 390 px uten horisontal overflyt og arkivkamp med lagret 103:00 samt resultat 2–2. PHP-, JavaScript- og pakkekontroller bestått. Produksjon er ikke endret.


## Eksterne lesekontroller og manuelle gates – 27. september 2026

### Hva denne oppfølgingen beviser

Grunnlaget er PR #28, head `c13adf7ecc2756893f8725a26419189a0199a1bd`.
Begge eksisterende Actions-kjøringene ble bekreftet fullført med success på denne commiten.
De tidligere **40/40 HTTP- og 37/37 kontraktkontrollene er historiske stagingresultater fra 26.09**;
de er ikke kjørt på nytt i denne oppfølgingen. Stagingdatabasen og det tidligere lokale
WordPress-miljøet er ikke tilgjengelig her. Ingen ny aktivering eller installasjon er utført.

`theme-next/scripts/read-only-probes.py` automatiserer direkte GET til de konkrete eksterne
kildene og to GET til en eksisterende statisk CSS-fil. Ingen WordPress-bootstrap, WP-CLI,
admin-AJAX, REST-handling, innlogging, cron, POST, import, cachetømming eller theme-bytte inngår.
Dette skillet er nødvendig: selv GET til kamp-/værruter kan skrive transients eller utløse
kampbytte; `rr_poll_rollover_selection()` kan også endre valgt kamp ved sidebesøk.

Skriptet har en lukket URL-liste, verifisert HTTPS, ingen redirects/retries/cookies/credentials,
10 sekunders tilkoblingsfrist og 20 sekunders totalfrist per kall, maks 2 MB per dokument og
maks 64 KiB ved valgfri strømprøve. Ingen cache-busting-parametre brukes. Råsvar finnes bare
i midlertidig lokal lagring under kontrollen og slettes; rapporten beholder tekniske headere,
størrelse, hash, tidspunkt og aggregerte funn, ikke navn, nyhetstekst, cookies eller lydopptak.
Vanlige tilgangslogger/cachefylling hos kildene kan følge av GET; det utføres ingen
applikasjons- eller konfigurasjonsendring på Radio Rubben.

| Kontroll | Automatisk bevis | Avgrensning |
|---|---|---|
| MET, Rubbestadneset | HTTP 200, JSON-felter, temperaturserie, oppdatering maks 6 timer gammel og dekning innen 3 timer fremover | Ikke WordPress sin transient-/fallback-flyt eller nettleservisning |
| Bømlo kommune RSS | HTTP 200, RSS/Atom, brukbare tittel/lenke-felt, antall og alder på nyeste sak | 30 dager er varselgrense, ikke et krav om daglig publisering. Ingen import eller publisering |
| NFF, kjent hjemmekamp 8985476 | To GET for tropper/hendelser; kampkort, Bremnes-referanse og seksjonsmarkører | Kildestruktur, ikke kjøring av PHP-importøren, riktig nåværende kampvalg eller semantisk validering av spillere/resultat |
| Statisk CSS/cache | To begrensede GET, HTTP/type/headere og sammenligning av SHA-256 | Bare gammel theme-ressurs. Ikke Next-cache, innloggingsisolasjon, CDN-purge eller lasttest |
| Radio.co, valgfritt | Begrenset prøve fra bekreftet offentlig `/s…/listen`-adresse | Lydbytes gir høyst WARN, aldri godkjent hørbar avspilling. Ingen strøm startes eller innstilling endres |

Kildeadressene er hentet fra `rr-site-functions/inc/weather.php`,
`rr-site-functions/templates/feed.php` og `rr-site-functions/inc/bremnes-poll-match.php`.
Ingen ny RSS-leverandør eller alternativ NFF-metode er valgt. Nettverkssperrer/challenges
skal rapporteres, ikke omgås.

### Gjentakbar kjøring og CI

Fra repositoryroten (Python 3 og curl):

```bash
python3 -m unittest discover -s theme-next/tests -p 'test_read_only_probes.py'
python3 theme-next/scripts/read-only-probes.py --source-ref <full-commit-sha> --output /tmp/rr-read-only.json
```

Valgfri `--stream-url` må være en bekreftet, offentlig HTTPS Radio.co-listen-adresse uten
påloggingsdata eller query. URL-en rapporteres; legg aldri inn en signert eller privat URL.
Ingen kjent aktiv URL forelå i dette oppdraget, så lydprøven er SKIP. En bevart innstilling
eller pauset spiller regnes ikke som vellykket lydtest.

Returkoder: **0** = alle utførte kontroller PASS; **1** = minst én FAIL/ERROR;
**2** = WARN/SKIP uten FAIL/ERROR. Manglende data og tidsavbrudd blir aldri PASS.
Selv kode 0 setter ikke `production_approved` til true. Rapporten angir grunnlagscommit og
SHA-256 av selve kontrollskriptet.

`Next theme — kontroll og ZIP` kjører de isolerte regresjonstestene som en vanlig byggkontroll.
En separat jobb samler eksterne observasjoner ved PR og manuell workflow-kjøring, uten
secrets eller skrivetilgang. Push-kjøringen utelater nettjobben for å unngå doble kildekall.
Resultater vises per kontroll i Actions Summary og lagres som `read-only-observations-<sha>`
i 14 dager. Forventede kildeavvik (retur 1/2) registreres uten å stoppe ZIP-bygg; manglende
rapport eller krasj stopper observasjonsjobben. **Grønn CI betyr ikke godkjente eksterne
gates**; les rapporten. Ingen deploy-jobb eller deploy-innstilling er endret.

### Faktisk kjøring 27.09.2026

15/15 isolerte regresjonstester besto. De dekker blant annet lukket URL-liste,
GET-begrensninger, frister, header-redigering, redirects, delvise svar, feilformater,
foreldet vær/RSS og at lydbytes ikke blir godkjent avspilling.

Siste lokale lesekjøring **08:17:16–08:17:56 norsk tid** ga **6 PASS, 1 ERROR, 1 SKIP**
(returkode 1). Dette er åtte observasjoner, ikke åtte godkjente integrasjoner:

| Observasjon | Faktisk resultat |
|---|---|
| MET | ERROR: tilkoblingstimeout, curl 28, ingen HTTP-status eller payload. En separat innledende HEAD svarte 200, men beviser ikke et vellykket GET eller gyldig værvarsel. Værgaten er åpen. |
| RSS | PASS: HTTP 200; 5 brukbare saker; nyeste publisert 25.09 kl. 12:12:30 UTC. |
| NFF tropper og hendelser | 2 PASS: HTTP 200 med forventede HTML-markører for kjent kamp. PHP-importøren er fortsatt uprøvd mot disse ferske svarene. |
| Statisk CSS | 3 PASS: to HTTP 200 og identisk SHA-256. Age økte fra 124 til 130. Ikke bevis for privat cache-isolasjon eller Next i produksjon. |
| Lyd | SKIP: ingen bekreftet aktiv strøm-URL. |

Siste maskinlesbare bevis: [read-only-2026-09-27.json](../tests/results/read-only-2026-09-27.json).
[Første forsøk](../tests/results/read-only-2026-09-27-initial.json) er bevart: 3 PASS,
3 ERROR og 1 SKIP med 5 sekunders tilkoblingsfrist. Fristen ble deretter økt til 10 sekunder
(totalfrist 20). Ingen feilresultater er omskrevet til bestått. Senere Actions-rapporter
kan variere med nettverk og kilder; de endrer ikke automatisk manuelle gates.

### Obligatoriske manuelle gates før eventuell produksjonsaktivering

Alle er **ÅPNE / IKKE GODKJENT**. Thomas er godkjenner; teknisk utfører dokumenterer hver
kontroll. En gate lukkes bare med dato/tid, miljø/URL, kandidatens commit og ZIP-hash,
utøver, forventet/faktisk resultat og dataminimert bevis. Ingen tokens, medlemsdata eller
skjermbilder med personopplysninger i PR. Avvik beholder gate åpen. En senere godkjenning
av disse gatene er ikke i seg selv tillatelse til deploy.

| Gate | Forutsetning og handling | Godkjenningskriterium / bevis |
|---|---|---|
| G1 `vipps-return` | Separat godkjent Vipps-testoppsett, registrert HTTPS-returadresse og disponibel testbruker. Test vellykket innlogging/retur, avbrudd/avslag, utløpt eller ugyldig state og utlogging. Ingen endring i produksjonens Vipps-oppsett. | Riktig lokal testkonto/rolle, sikker retur til Min Rubben, feiltilfeller avvist uten innlogging, ingen kobling til feil bruker. Dokumenter sanert hendelsesrekkefølge; lokal passordinnlogging teller ikke. |
| G2 `wordpress-fresh-import` | Kjør fersk NFF-tropp/hendelser, RSS og vær i disponibelt stagingmiljø med kun nødvendige eksterne domener åpnet. Behold e-post/cron/betaling blokkert. Staginglagring kan da endres som del av en særskilt test, aldri produksjon. | Kandidatens ekte PHP-importør/renderer godtar data for korrekt FIKS-ID og begge lag; låst tropp respekteres; RSS tittel/lenke/kilde og værsted/tid vises korrekt. Timeout/ugyldig data beholder tidligere gyldige data. Noter hentetid og resultat uten spillerlister/medlemsdata. GET-probene alene lukker ikke gaten. |
| G3 `audible-browser-playback` | Thomas bekrefter en aktiv, tillatt strøm/teststrøm. Prøv kandidatens spiller i staging i iOS Safari og en desktopnettleser med brukertrykk på play. Behold produksjonens pauseinnstilling. | Hørbar korrekt sending, play/pause og gjenopptak, én lydinstans, forståelig feil ved nettbrudd/avbrutt strøm. Noter nettleser og tidspunkt. MIME/HTTP/bytes alene teller ikke. Hvis sending fortsatt skal være pauset, dokumenter dette som uttrykkelig produktbeslutning; ikke merk aktiv lydtest som bestått. |
| G4 `mail-cron-payment-automation` | Bruk mottakersink/testinnboks og sandbox for eventuelle betalinger/webhooks. Inventer cron før/etter kandidat i staging. Ingen ekte utsending, belastning eller tredjepartsjobb fra kopien. | Riktig mottaker/payload i sink, ingen duplikate jobber eller utilsiktet automasjon, bekreftet sperre mot produksjonsmottakere. Funksjoner uten testmiljø forblir åpne eller dokumenteres særskilt deaktivert/godkjent uten å kalles bestått. |
| G5 `cache-operations-release` | Teknisk gjennomgang av faktisk host/MySQL/PHP/utvidelser, cache/CDN-regler og logger. Kontroller to adskilte testøkter i godkjent staging, ferske kildehasher, koordinert backup med gjenopprettingsbevis, tilbakeføring og rolig byttevindu. | Ingen privat side/nonce/stemme-/quizrespons deles mellom økter; riktig cache-unntak og ferske data etter oppdatering, ingen PHP/JS-feil, akseptabel responstid. Hash-avvik siden migreringsgrunnlaget avklart. Thomas godkjenner utseende, plan og ansvar. Produksjonspurge/bytte og nødvendig etterkontroll krever egen senere tillatelse; siste driftskontroll kan ikke erklæres fullført på forhånd. |

**Beslutning: fortsatt stagingkandidat. Ingen merge, nytt theme eller produksjonsdeploy.**
