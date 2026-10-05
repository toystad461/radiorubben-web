# Radio Rubben – spillerkamper 1.2.0

Standardvisningen er nå en kompakt, sammenleggbar spillerrekke med vannrett blaing på mobil. Alle aktive spillere som er valgt eksplisitt eller godkjent i Bømlo-listen vises én gang. Godkjenning krever samsvar mellom person-ID og spillerprofil; ventende og avviste kandidater tas ikke med. Manuelt fulgte medlemmer av gruppen `bomlo-away` tas også med. Årets lag i profilen kombineres med eksisterende ekstra lag, og lagets klubb må stemme med registrert klubb. Spillerne sorteres stigende etter nærmeste kamp, med stabile navnetreff ved samme avspark. Spillere uten bekreftet kamp står til høyre uten merknad. Ukjent tropp har ingen etikett. Spillerbildet fra profilens fremhevede bilde brukes dersom det finnes; ellers vises et nøytralt spillerikon. Det hentes ingen portrettbilder automatisk fra andre nettsteder. Kortet bruker første- og etternavn visuelt og fullt navn som tilgjengelig etikett.

`[rr_spillerkamper]` viser spillerrekken; `player="3942773"` avgrenser til én person. Den tidligere store visningen er fortsatt tilgjengelig med `[rr_spillerkamper layout="cards" limit="3"]`. `limit` gjelder bare store kampkort. MyGame-knappen åpner en bekreftet kampside også når en konkret TV 2-sending ennå ikke er tilgjengelig. Identitet, avspark og planlagt status må fortsatt stemme med NFF. «TV 2» vises som en egen lenke når sendings-ID-en er bekreftet. Ingen klokkeslett brukes til å hevde at spilleren spiller nå.

Den kompakte oppdateringen har 13 egne kontroller i tillegg til de 63 eksisterende, samt mobil/desktop-kontroll av høyde, vannrett blaing, sammenfolding og utløpte kilde-/troppsdata. Førstegangsinstallasjonen nedenfor er historikk; 1.1.0 ble publisert fra `7e636c41ffaccb27d2a983408d149617164140a6` med `deploy-player-widget-compact.sh`. Jobb [37209335706](https://github.com/toystad461/radiorubben-web/actions/runs/37209335706) bestod, inkludert fersk MyGame-side for Fana–Åsane 2, uendrede spillerinnstillinger og begge offentlige plasseringer ved 360/1280 px. Backup ligger utenfor webrot i `.radiorubben-deploy/backups/player-widget-compact-7e636c41ffaccb27d2a983408d149617164140a6/code.tar.gz`. Kun åtte widgetfiler ble byttet/lagt til; temaet er uendret. Sport-sidens eksisterende widgetgruppe er gjort kortere, slik at overskriften ikke gjentas. Ingen main-merge er utført.

Viser kommende kamper for valgte lokale spillere med den godkjente Radio Rubben-profilen, bekreftede MyGame-lenker og separat troppsstatus. Ingen video bygges inn. Se-knappen åpner den konkrete TV 2 Play-sendingen; abonnement håndteres der.

## Oppdatering 1.2.0

15 egne kontroller dekker automatisk godkjenning, flere lag, sortering, flyttet kamp, gamle klubber, pausing og identitetsvern. Oppdateringen bruker `deploy-player-widget-roster.sh`, med kontrollsum mot 1.1.0 og sikkerhetskopi av seks berørte runtime-filer utenfor webrot. Ingen tema- eller sideinnholdsendring. Manglende godkjente NFF-profiler fylles gjennom Fotballrobotens egen `Players::refresh`; godkjenninger og manuelle widgetvalg endres ikke. Nærmeste kamp per spiller prioriteres ved kildeinnhenting.

## Installasjon og plassering

1. Installer kun denne pluginmappen på WordPress og aktiver pluginen. PHP 8.0+, DOM og mbstring kreves.
2. Åpne **Innstillinger → Radio Rubben – spillerkamper**. Spillerlisten leses fra den eksisterende Fotballroboten (`rr_robot_player` og `rrfr_player_{id}`); ingen av disse dataene endres.
3. Slå på kampoversikten. Godkjente aktive Bømlo-spillere og deres registrerte lag følger automatisk med. Bruk manuelle valg for ekstra spillere eller lag. Andre private profiler tas ikke med. Pause en spiller i spillerlisten for å fjerne vedkommende.
4. Klikk «Oppdater neste runde», eller vent på WP-Cron. Hver runde behandler ett lag og inntil to kamper. Forhåndsvisningen står på samme innstillingsside.
5. Legg en **Kortkode**-blokk på ønsket side: `[rr_spillerkamper]`. Bruk `[rr_spillerkamper layout="cards" limit="3"]` for store kampkort eller `[rr_spillerkamper player="3942773"]` for én NFF-person-ID. Alternativt velges **Radio Rubben – spillerkamper** under Utseende → Widgeter.

Ingen forsidemal endres automatisk. Kortet er uavhengig av aktivt theme og kan også brukes etter et senere theme-bytte.

## Lagvalg for den eksisterende listen

Følgende er kontrollert mot lagrede spillerdata 4. oktober 2026. Bekreft aktuelle valg ved aktivering; dette er ikke en innebygd hardkodet spillerliste.

| Spiller | NFF-person-ID | Aktuelle lag-ID-er til første oppsett |
|---|---:|---|
| Tiril Elisabeth Sellevold-Øystad | 3942773 | **35897** (Brann 2 kvinner), 108426, 114647, 21080 |
| Lasse Nathaniel Høgmo Breivik | 3909887 | 113002, 126, 20705, 517, 84403 |
| Troy Engseth Nyhammer | 3646624 | 2 |
| Sander Håvik Innvær | 3584397 | 2 |
| Anna Engeseth Lie | 3909852 | 210681 |

Lag 35897 fanges ikke av Tirils daværende sesongstatistikk; det velges derfor eksplisitt. Valg av lag betyr at lagets kamper følges, aldri at spilleren er tatt ut. Annas kildeoppdatering feilet ved siste kontroll 4. oktober; sist lagret grunnlag var fra 3. oktober. Lagtilhørighet må bekreftes mot den offentlige lagsiden. Troy har også Haugesund 202 i historikken; det skal ikke legges til som nåværende lag. Anna og Sander kan velges selv om gruppemerket `bomlo-away` mangler.

## Datakontroll

- Terminlisten må inneholde det valgte laget. Klubb-ID fra lagsiden må finnes i spillerens sist registrerte klubbtilknytning. Manglende eller endret klubb gjør at kortet holdes tilbake. Årets statistikk gir kandidatlag; bare lag i registrert klubb brukes, uten å antyde uttak.
- Kampene dedupliseres på NFF-kamp-ID, og flere fulgte spillere samles i ett kampkort. Kortkodefilteret bruker NFF-person-ID. Pausede, fjernede eller ikke valgte spillere tas umiddelbart ut av ny rendering.
- MyGame-oppslag bruker `https://kampoversikt.mygame.no/match/fiks-no{kamp-ID}`. En HTTP 200 eller en eksisterende kampside er utilstrekkelig.
- Før «Se på TV 2 Play» vises, må nøyaktig én `SportsEvent` ha samme hjemmelag, bortelag og avspark som NFF, status `EventScheduled` og én entydig HTTPS `/gpid/{UUID}`-lenke på `play.tv2.no` med `utm_content=fiks-no{samme kamp-ID}`. Det er en bekreftet sendingslenke, ikke en test av videoavspilling eller lovnad om at kameraet sender.
- Parseren bruker observerte offentlige HTML-kontrakter, ikke et dokumentert MyGame-API. Endret format, navneavvik, omberamming, ugyldig URL, manglende lenke eller kildefeil gir «Sending ikke bekreftet».
- Tropp vurderes separat med NFF-person-ID i riktig kamp og start-/reserveoverskrift. Ukjent, duplisert, strøket eller gammel oppføring skjules i den kompakte visningen; store kampkort beholder «Tropp ikke bekreftet». Ingen automatisk påstand om deltakelse, kampresultat eller LIVE-status.
- Klubblogoer godtas kun fra MyGames observerte klubbmedieadresse og må kunne kobles til riktig lagnavn i kampens HTML. Ved fravær eller bildefeil vises en initial. Radio Rubben-logoen er fra brukerens logopakke, 30.09.2026, uendret SVG uten verdilinje.

## Drift og feilhåndtering

Innhenting skjer bare i bakgrunnsjobben eller etter administratorens «Oppdater neste runde». En sidevisning gjør ingen kildeforespørsler. Femminutters WP-Cron behandler maks ett lag (tidligst hvert 15. minutt) og to kamp-par per runde: maks fem forespørsler med fem sekunders tidsgrense, ingen redirects og 2 MB responsgrense. Flere lag fordeles på senere runder. WP-Cron krever trafikk eller en allerede konfigurert serverjobb; installasjonen oppretter ikke en ekstern automasjon.

Terminliste blir ugyldig etter seks timer eller umiddelbart etter et mislykket nytt oppslag. Sending og tropp må være kontrollert de siste 30 minuttene. Matchdetaljer er ugyldige når terminlistens kampdata endrer seg. Ved mislykket ny kontroll slettes tidligere positive bekreftelser. Kamper eldre enn tre timer skjules; nærmeste planlagte kamp kan ligge mer enn sju dager frem i tid, og ferdige/avlyste/utsatte kamper filtreres bort. Ved passert avspark står «Kampstart passert», aldri en tidsberegnet LIVE-status.

Fullsidecache kan ellers holde på tidligere HTML. **Unnta siden fra fullsidecache, eller bruk maksimalt 60 sekunders cache og tøm cache når spillere slås av eller fjernes.** Med JavaScript aktivt håndheves de absolutte utløpstidene også på allerede åpne eller mellomlagrede sider, hvert 30. sekund og ved tilbakeknappen. Uten JavaScript gjelder serverrenderingens siste status til neste sidehenting. Nettleseren gjør ingen nye API-kall.

Innstillinger krever administrator og nonce. Foreldede adminskjemaer avvises. Bakgrunnsjobben bruker lås og reviderte innstillinger; «Oppdater neste runde» kan frigjøre en lås som har stått i mer enn fem minutter etter en avbrutt PHP-prosess. Kilder og feil er synlige for administrator. Private spillerdata, notater og redaksjonelle hendelser eksponeres ikke via offentlige REST-ruter (pluginen har ingen slike ruter).

## Tester

`php tests/run.php` kjører kontrakt- og tilstandstester med simulerte HTTP-svar. `mygame.html` er et minimert offentlig svar for kamp 8989882, lest 04.10.2026; sendingslenke og tid er bevart. NFF-fixture-tabeller følger eksisterende Fotballrobot-format, med syntetiske kontrollrader. Oppstillingsfixturen er uttrykkelig syntetisk og dokumenterer ikke Tirils faktiske uttak.

`tests/preview.php` produserer en lokal layoutprøve uten WordPress. Dato/tid tilpasses kjøringen; prøven publiserer ingenting. `scripts/player-widget-ui.cjs` kontrollerer 320/360/736/1100 px, bilder, lenke, detaljfelt og utløpte data. Klubblogo-fixturene er nedskalerte kopier av de to offentlige logoene som den observerte MyGame-siden bruker: `fiks-no3302.png` og `fiks-no781.png`. Ingen nettverk, video, e-post eller AI-kall brukes i testene.

## Førstegangsinstallasjon på Radio Rubben 4. oktober 2026

Versjon 1.0.1 ble installert og aktivert selektivt fra `ed33789911f21a0e11edff732cf71c8ed8c36199`, etter brukerens publiseringsgodkjenning. Produksjonsjobben er [37199403512](https://github.com/toystad461/radiorubben-web/actions/runs/37199403512). Main er ikke flettet eller bredt publisert, og Fotballrobotens kode/spillerdata er uendret.

- Forsiden: to nærmeste kamper over siste nyheter, via ett `rrpw_homepage`-hook i eksisterende `front-page.php`. Sport-side 769: `[rr_spillerkamper limit="6"]` ved `#bomlo-spillere`.
- Valgte lag: Tiril → Brann 2 / 35897; Lasse → Åsane 2 / 20705; Troy og Sander → Tromsø / 2; Anna → Haugesund 2 / 210681. Øvrige lag i tabellen ovenfor er forslag, ikke aktive valg. Flere lag velges i administratorinnstillingene ved behov.
- Fire ferske lagkilder bestod fra webhotellet. NFF-kamp 8989882 ga Sogndal–Brann 2 kl. 14.00, og MyGame bekreftet TV 2-lenken. Tiril var oppført som innbytter ved kontroll kl. 13.39. Dette er et historisk kontrollresultat, ikke en fast troppsstatus.
- `rrpw_refresh` er registrert hvert femte minutt og er observert kjørt automatisk. WP-Cron er avhengig av sidetrafikk.
- Forsiden (når `rrpw_homepage_enabled` er på) og sider med kortkoden får `DONOTCACHEPAGE`, WordPress `nocache_headers()` og LiteSpeeds dokumenterte `litespeed_control_set_nocache`-signal. Produksjonshodet `Cache-Control: no-cache, must-revalidate, max-age=0, no-store, private` er kontrollert. Ved andre temaplasserte kortkoder/widgeter må cache-unntak fortsatt konfigureres eksplisitt.
- 63 kilde-/tilstandskontroller, lokale layoutkontroller ved fire bredder og ekte nettleserkontroll med aktivt theme ved 360/1280 px bestod. Selve TV 2-videoavspillingen er ikke testet.

Publiseringsjobben er en avgrenset førstegangsinstallasjon: den nekter å erstatte en eksisterende pluginmappe eller en forside med endret kontrollsum. Ikke kjør den på nytt som en generell oppdatering. Skjermbildesjekken bruker denne konkrete publiseringskampen og er heller ikke en varig kampavhengig CI-test.

Tilbakeføring: deaktiver bare `radio-rubben-player-widget` og fjern kortkodegruppen på Sport-siden. Forsidehooket blir da inaktivt. Original forside er sikkerhetskopiert utenfor webrot i `.radiorubben-deploy/backups/player-widget-ed33789911f21a0e11edff732cf71c8ed8c36199/front-page.php`; sammenlign nyere temaendringer før eventuell gjenoppretting. Deaktivering rydder pluginens egen cronjobb. Eksisterende artikler og fulgte spillerprofiler blir stående.
