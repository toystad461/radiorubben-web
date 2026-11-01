# Fotballdata: tomme svar og kildefeil, 01.11.2026

## Bekreftet årsakskjede

GitHub-run [41282316294](https://github.com/toystad461/radiorubben-web/actions/runs/41282316294)
brukte runner `f31b4765f6ec8020d97eb6779e37b344002cb16d`. Checkout, pin-kontroll,
SSH og WordPress-bootstrap kom gjennom. `run.php:25` kastet deretter fordi
`rrfr_match_followup_error` eksisterte. Dette beviser ikke et nytt API-forsøk i
akkurat denne kjøringen: `tick()` returnerer under retry-venting, mens runneren
fortsatt rapporterer den uavklarte kildefeilen.

Autentisert lesekontroll viser aktiv WordPress-plugin 0.10.8 og 36 forsøk
(tidligere dokumentert 33), fortsatt uten HTTP-status i feiloptionen.
Aktiv `fotballdata.php`, `match-followup.php` og kildeinnsamlingen ble lest.
Den registrerte meldingen oppstår ved WP_Error eller HTTP ulik 200, **før**
JSON/kamplisten tolkes. Den nøyaktige statusen eller transportårsaken kan ikke
rekonstrueres fra den gamle meldingen. Tomme kamper er ikke påvist rotårsak.

Flyt: Actions → fastlåst eksisterende PHP-runner → WordPress →
`MatchFollowup::tick()` → `ClubCoverage::collect()` → Fotballdata klubbens lag,
deretter klubbens kamper → validering → aktuelle kamper siste sju døgn →
eksisterende MatchJobs/klubbflyt → lagret status og runnerkvittering.
Kampdetaljer hentes først av de eksisterende artikkeljobbene, ikke for en tom
kampliste. En lagret tom lokal liste er ikke bevis for at eksterne kamper ikke
finnes; vanlig discovery beholdes.

`RadioRubben-robot` main er kontrollert: det er en separat TypeScript-grunnstruktur,
ikke påvist operativ Fotballrobot. Ingen ny implementering eller scheduler er laget der.

## Rettelsen i eksisterende PR #106

- Gyldig `Matches: []`, ingen aktuelle kamper og uendrede relevante kampdata
  gir normal `no_work`. Feiloptionen slettes først etter vellykket innsamling
  og behandling. Feiltelleren øker ikke ved tomme data eller retry-venting.
- Gyldig `Teams: []` eller lag som alle faller utenfor gjeldende redaksjonelle
  alders-/seniorregler er normalt. Da dokumenterer lagresponsen hvorfor
  kampforespørselen ikke er nødvendig.
- Manglende/null/objektformede lister, feil klubb, ugyldige rader, ugyldig JSON,
  tom HTTP-body og HTTP-feil forblir tekniske feil. HTTP 204 har ingen etablert
  kontrakt i denne integrasjonen og tolkes derfor ikke som en gyldig tom kampliste.
- Kategorier: configuration, authentication (401), access (403), rate_limit
  (429), service (5xx), http, network, timeout, response_body, invalid_json,
  schema og processing. Bare faste kategorier/statuskoder lagres; aldri URL,
  legitimasjon, response-body eller rå transport-/unntakstekst.
- Eksisterende eksponentielle retry beholdes, maksimalt én gang i timen etter
  gjentatte feil. Tilgang/configuration kontrolleres igjen etter 24 timer.
  Retry-After for 429/503 respekteres opp til 24 timer. Det opprettes ingen ny retry-scheduler.
- Reelle feil bevarer tidligere verifiserte kampdata. Runneren fortsetter å
  feile synlig også mens en uavklart feil venter på retry. Den kamufleres ikke som no_work.
- Runnerens normale kvittering får `source_status`. Lås-konkurranse rapporteres
  som locked, ikke som en fersk tom kildekontroll. Gammel plugin støttes med legacy.
- Eksisterende PR-rettelse for `rrfr_club_results` og manglende behandler beholdes.
  Redaksjonell godkjenning, publisering og idempotente artikkeljobber endres ikke.

## Filgrunnlag og tester

Main var `a1f41bd7a211604aaa6e7c271205bce5a50a4dff`; eksisterende #106-head var
`6951cd4e40d8fb1557c80c881061cf8183c1d994`, base `5169765446b75f5c983695940241985beda2f7ad`.
Tre eksisterende plugin-filer er hentet fra
`10e9684d6061deccc9bdd5ac39d3cf8251f4bfc4` i en separat importcommit før rettelsen.
Main/#106 inneholdt ikke disse filene. Dette er et selektivt filsett, **ikke et
installerbart komplett plugin**. Lesing gjennom WPVibe er kildekontroll, ikke
byteeksakt produksjonshash; ferske serverhasher kreves før utrulling.

Lokalt PHP 8.4: 22 nye adapter→oppfølging→runner-scenarioer og 9 eksisterende
runner-scenarioer består. I tillegg består eksisterende integrasjonstester:
43 kampoppfølgingskontroller, 8 kildekontroller, 66 klubbkontroller, 12 datokontroller
og 11 tids-/duplikatkontroller. Alle bruker syntetiske data/modellstubber.
Kun den gamle forventningen om feil ved gyldig tom lagliste er endret i
integrasjonstestkopien; øvrige tester kjøres uendret fra fast kildecommit.

```sh
php scripts/tests/fotballdata-followup.php
php scripts/tests/fotballrobot-cron.php
python scripts/tests/fotballdata-integration.py
php -l scripts/fotballdata-diagnose.php
```

Eksisterende PR-testworkflow er utvidet for PHP 8.2/8.3. Operativ workflow,
runner-pin og planlegging er ikke endret. Lokal test er ikke produksjonsbevis.

## Gjenstående diagnose og kontrollert utrulling

`scripts/fotballdata-diagnose.php` er en klargjort, lesende engangsprobe for den
eksisterende host-CLI-forbindelsen. Den gjør høyst to kildekall, skriver bare
trygge statusfelt, antall og SHA-256 for de tre aktive filene, og kjører aldri
tick, cron eller forfattere. Den er syntakskontrollert, men ikke kjørt i produksjon.
På VPS/host brukes eksisterende WP-CLI-bootstrap og korrekt WordPress-rot med
`wp eval-file <absolutt-sti-til-diagnosefil> --use-include`. Ikke legg filen i webroten.
WPVibes CLI-emulator støtter ikke eval-file; den må ikke forveksles med host-CLI.

Før produksjonsretting: kjør proben via eksisterende godkjent forbindelse,
avklar HTTP-/nettverksårsaken, sammenlign ferske hasher med importgrunnlaget og
gjennomgå eventuell drift. Bevar privat sikkerhetskopi av bare berørte filer og
gammel runner-pin. Rull ut kun de tre gjennomgåtte plugin-filene og den testede
runner-pinnen etter separat produksjonsgodkjenning. Ikke installer hele grenen.
Kontroller neste ordinære kjøring, no_work ved gyldig tom/uforandret kilde,
og riktig kategori ved kildefeil. Ikke slett feiloptionen manuelt for å gjøre
Actions grønn. Rollback tilbakefører filer/pin, aldri artikler, køer eller database.

Ingen produksjonskode, cron, secrets, artikler, PR-merge eller deploy er endret.
Uregelmessige GitHub-startintervaller er fortsatt et separat, uavklart forhold.
