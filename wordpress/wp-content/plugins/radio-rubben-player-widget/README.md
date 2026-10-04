# Radio Rubben – spillerkamper 1.0.0

Viser kommende kamper for valgte lokale spillere med den godkjente Radio Rubben-profilen, bekreftede MyGame-lenker og separat troppsstatus. Ingen video bygges inn. Se-knappen åpner den konkrete TV 2 Play-sendingen; abonnement håndteres der.

## Installasjon og plassering

1. Installer kun denne pluginmappen på WordPress og aktiver pluginen. PHP 8.0+, DOM og mbstring kreves.
2. Åpne **Innstillinger → Radio Rubben – spillerkamper**. Spillerlisten leses fra den eksisterende Fotballroboten (`rr_robot_player` og `rrfr_player_{id}`); ingen av disse dataene endres.
3. Velg spillerne som skal vises offentlig, velg lag-ID-er og slå på kampoversikten. Det er bevisst ingen automatisk publisering av alle private spillerprofiler ved aktivering.
4. Klikk «Oppdater neste runde», eller vent på WP-Cron. Hver runde behandler ett lag og inntil to kamper. Forhåndsvisningen står på samme innstillingsside.
5. Legg en **Kortkode**-blokk på ønsket side: `[rr_spillerkamper]`. Bruk `[rr_spillerkamper limit="3"]` for flere kamper eller `[rr_spillerkamper player="3942773"]` for én NFF-person-ID. Alternativt velges **Radio Rubben – spillerkamper** under Utseende → Widgeter.

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

- Terminlisten må inneholde det valgte laget. Klubb-ID fra lagsiden må finnes i spillerens sist registrerte klubbtilknytning. Manglende eller endret klubb gjør at kortet holdes tilbake. Statistikk er forslag til lagvalg, ikke automatisk lagpåmelding.
- Kampene dedupliseres på NFF-kamp-ID, og flere fulgte spillere samles i ett kampkort. Kortkodefilteret bruker NFF-person-ID. Pausede, fjernede eller ikke valgte spillere tas umiddelbart ut av ny rendering.
- MyGame-oppslag bruker `https://kampoversikt.mygame.no/match/fiks-no{kamp-ID}`. En HTTP 200 eller en eksisterende kampside er utilstrekkelig.
- Før «Se på TV 2 Play» vises, må nøyaktig én `SportsEvent` ha samme hjemmelag, bortelag og avspark som NFF, status `EventScheduled` og én entydig HTTPS `/gpid/{UUID}`-lenke på `play.tv2.no` med `utm_content=fiks-no{samme kamp-ID}`. Det er en bekreftet sendingslenke, ikke en test av videoavspilling eller lovnad om at kameraet sender.
- Parseren bruker observerte offentlige HTML-kontrakter, ikke et dokumentert MyGame-API. Endret format, navneavvik, omberamming, ugyldig URL, manglende lenke eller kildefeil gir «Sending ikke bekreftet».
- Tropp vurderes separat med NFF-person-ID i riktig kamp og start-/reserveoverskrift. Ukjent, duplisert, strøket eller gammel oppføring gir «Tropp ikke bekreftet». Ingen automatisk påstand om deltakelse, kampresultat eller LIVE-status.
- Klubblogoer godtas kun fra MyGames observerte klubbmedieadresse og må kunne kobles til riktig lagnavn i kampens HTML. Ved fravær eller bildefeil vises en initial. Radio Rubben-logoen er fra brukerens logopakke, 30.09.2026, uendret SVG uten verdilinje.

## Drift og feilhåndtering

Innhenting skjer bare i bakgrunnsjobben eller etter administratorens «Oppdater neste runde». En sidevisning gjør ingen kildeforespørsler. Femminutters WP-Cron behandler maks ett lag (tidligst hvert 15. minutt) og to kamp-par per runde: maks fem forespørsler med fem sekunders tidsgrense, ingen redirects og 2 MB responsgrense. Flere lag fordeles på senere runder. WP-Cron krever trafikk eller en allerede konfigurert serverjobb; installasjonen oppretter ikke en ekstern automasjon.

Terminliste blir ugyldig etter seks timer eller umiddelbart etter et mislykket nytt oppslag. Sending og tropp må være kontrollert de siste 30 minuttene. Matchdetaljer er ugyldige når terminlistens kampdata endrer seg. Ved mislykket ny kontroll slettes tidligere positive bekreftelser. Bare kamper fra de siste tre timene til sju dager fremover vises, og ferdige/avlyste/utsatte kamper filtreres bort. Ved passert avspark står «Kampstart passert», aldri en tidsberegnet LIVE-status.

Fullsidecache kan ellers holde på tidligere HTML. **Unnta siden fra fullsidecache, eller bruk maksimalt 60 sekunders cache og tøm cache når spillere slås av eller fjernes.** Med JavaScript aktivt håndheves de absolutte utløpstidene også på allerede åpne eller mellomlagrede sider, hvert 30. sekund og ved tilbakeknappen. Uten JavaScript gjelder serverrenderingens siste status til neste sidehenting. Nettleseren gjør ingen nye API-kall.

Innstillinger krever administrator og nonce. Foreldede adminskjemaer avvises. Bakgrunnsjobben bruker lås og reviderte innstillinger; «Oppdater neste runde» kan frigjøre en lås som har stått i mer enn fem minutter etter en avbrutt PHP-prosess. Kilder og feil er synlige for administrator. Private spillerdata, notater og redaksjonelle hendelser eksponeres ikke via offentlige REST-ruter (pluginen har ingen slike ruter).

## Tester

`php tests/run.php` kjører kontrakt- og tilstandstester med simulerte HTTP-svar. `mygame.html` er et minimert offentlig svar for kamp 8989882, lest 04.10.2026; sendingslenke og tid er bevart. NFF-fixture-tabeller følger eksisterende Fotballrobot-format, med syntetiske kontrollrader. Oppstillingsfixturen er uttrykkelig syntetisk og dokumenterer ikke Tirils faktiske uttak.

`tests/preview.php` produserer en lokal layoutprøve uten WordPress. Dato/tid tilpasses kjøringen; prøven publiserer ingenting. `scripts/player-widget-ui.cjs` kontrollerer 320/360/736/1100 px, bilder, lenke, detaljfelt og utløpte data. Klubblogo-fixturene er nedskalerte kopier av de to offentlige logoene som den observerte MyGame-siden bruker: `fiks-no3302.png` og `fiks-no781.png`. Ingen nettverk, video, e-post eller AI-kall brukes i testene.

## Før aktivering på Radio Rubben

Dette er kildekode og testet implementasjon. Pluginen er ikke installert eller aktivert av denne endringen. Ingen eksisterende PR er endret, ingen merge eller bred deploy er utført.

1. Installer kun denne pluginen, uten `tests/`. Ikke kjør repositoryets brede WordPress-publisering for dette.
2. Bekreft en fersk NFF-terminliste og kampside fra WordPress-serveren. Direkte lesing fra utviklingsmiljøet ga HTTP 403; dette er ikke en vellykket live-test av NFF-adapteren. Nettlesing og Fotballrobotens eksisterende adapter dokumenterer formatet, og kontrakttestene består.
3. Start med Tiril / lag 35897 og kontroller MyGame-lenke for kamp 8989882 mens den er aktuell. Deretter de øvrige godkjente lagene. Ikke bruk fixture-data i produksjon.
4. Bekreft cronkjøring og riktig cacheoppsett. Prøv deretter kortkode på en WordPress-kladd og kontroller med aktivt theme før ønsket plassering publiseres.
5. Tilbakeføring: fjern kortkoden/widgeten og deaktiver bare denne pluginen. Dens cronjobb ryddes ved deaktivering. Ingen eksisterende spillerdata eller artikler er endret.
