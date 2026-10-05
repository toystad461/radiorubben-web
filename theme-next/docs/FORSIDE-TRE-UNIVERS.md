# Ny forside: tre univers — kun staging

Utgangspunkt er Next PR #28, commit `9119a54d3ea1017cd946b892345e95d1c293e143` (01.10.2026). Dette inkluderer profilporteringen fra PR #35: originale SVG-logoer, Arial, svart/hvitt og profilrødt #FF001B. Forsidearbeidet skal være en separat PR mot `theme/next-2.0.0-rc.1`, ikke mot main.

## Kartlegging før endring

- Main `bbcc9e76` er en eldre One-import. Next står åpen og er ikke produksjonsaktivert ifølge PR #28. One-profil PR #35 står også åpen; produksjonsstatus er ikke bekreftet gjennom serverlesing i dette arbeidet.
- Next-forsiden har fire presentasjonsdeler: hero, medlemsinngang, siste artikler og kommunefeeder, etterfulgt av vær, om-seksjon, sponsorer og bevart sideinnhold.
- `rr-site-functions` flytter kamp, Dagens Bremnesing, speaker, quiz, medlem og vær ut av temaet. Den eksisterende broen og tidligere stagingrapport beholdes.
- Felles `ui.js` styrer én `rr-audio`, alle play-knapper, volum, tilkoblingsfeil og mobilmeny. Den nye forsiden skal bruke samme avspiller.
- Radio.co-pluginet har lagret strøm-URL og en status-transient. Forsiden leser eksisterende cache uten nye eksterne forespørsler.
- WordPress `post_status=publish` er offentlig redaksjonell inngang. Ingen robotutkast, private innlegg eller passordbeskyttede saker brukes. Sport med underkategorier skilles fra nyhetene.
- Kampkort bruker Next-kontrakten `rr_theme_match_data`. Stemmer som er åpne eller avspark som er passert er ikke bevis på livekamp.

## Stagingbruk

Velg «Tre univers – stagingkandidat» i Tilpass → Radio Rubben – presentasjon på isolert WordPress-staging med Next og funksjonsbroen. `classic` forblir standard. Arkiv på forsiden, passordbeskyttet innhold og blokkmodus beholder sine eksisterende grener. Ingen automatisk innstillingsmigrering.

Radio er normal hovedinngang. Faktisk DOM-rekkefølge endres ved dokumentert prioritering; skjermleser og tastatur følger samme rekkefølge som den visuelle. En livekamp prioriteres før livesending, deretter en redaktørvalgt stor lokal sak. De øvrige universene er alltid tilgjengelige med ankerlenker og den faste avspilleren.

`rr_home_radio_data` og `rr_home_sport_data` er presentasjonsadaptere. Live må være PHP `true` med `observed_at` og `expires_at` som Unix-sekunder. Observasjonen kan ikke være eldre enn 600 sekunder eller ligge i framtiden, og utløpstiden kan ikke ligge mer enn 600 sekunder etter observasjonen. Provider kan bruke kortere vindu. Radio krever også konfigurert strøm. Gamle statiske `rr_live_status` eller kampkortets stemmestatus promoterer ikke universene. Automatisk radio-LIVE krever derfor tilkobling av tidsstemplet sendestatus; cache-tittelen fra Radio.co alene hevder ikke livesending.

Sport leser eksisterende offentlige `rr_match`-innlegg og Nexts RRLive-metadata. Bekreftet `live` med `rr_last_synced` gir et 180-sekunders vindu. Bekreftede sluttscorer og kommende kamper vises fra de samme postene. Tre avgrensede spørringer henter live, ferdig og planlagt separat; nærmeste planlagte kamp velges etter faktisk avsparkstid. Eksisterende kampkort fra funksjonsbroen er fallback. Ingen NFF-henting eller oppdatering av kampdata utføres av forsiden.

Radiofelter: `stream_url`, `artist`, `title`, `next_program`. Sport: `match` i eksisterende kortkontrakt, `last_result` som ren tekst og `upcoming` som en liste av rene tekstlinjer. Manglende data gir tydelig tomtilstand og lenke til eksisterende kampoversikt, aldri eksempelresultater i WordPress.

En stor sak velges eksplisitt gjennom `rr_home_major_story_id` og dato/klokkeslett i `rr_home_major_story_until` (nettstedets tidssone); innlegg må være offentlig publisert, uten passord og uten Sport-kategori eller underkategori. Nyhetspromoteringen utløper. Innleggsteksten endres ikke.

## Samkjøring som fortsatt kreves før theme-bytte

PR #28s fem manuelle aktiveringsporter gjelder fortsatt: Vipps, faktisk import/cache/visning, hørbar lyd, utsending/automasjon og endelig cache/drift/backup. Tidligere 40/40 HTTP- og 37/37 kontrakttester er historiske resultater fra 26.09, ikke tester av denne forsiden.

Nyere selektivt aktiverte endringer finnes i åpne PR-er: #32 (bortekamp), #33 (quiz-retur), #45/#47 (spillerwidget), samt Fotballrobotens stablede oppfølginger gjennom #52. PR-tekst dokumenterer produksjonsendringer selv om main er eldre. De skal ikke erstattes med Nexts eldre kopier. Før et faktisk theme-bytte må ferske runtime-hasher og skjulte theme-avhengigheter kontrolleres, relevante funksjonsrettinger portes med egne regresjonstester og det nåværende sideinnholdet verifiseres. Denne PR-en deployer ingen av dem.

## Produksjonsgrense og godkjenning

Endringer begrenses til `theme-next/` og testworkflow uten deploy. Ingen endring i `wordpress/`, ingen merge, theme-aktivering, produksjonsdeploy, robotkjøring, e-post eller artikkelpublisering. Før endelig godkjenning skal reell WordPress-staging og strømavspilling prøves. En lokal fixture-forhåndsvisning er bare design- og komponentkontroll og skal merkes med eksempelinnhold.

## Verifisering 05.10.2026

- 38 isolerte kontroller bestått i PHP 8.2 WASM: prioritering, friske/utløpte signaler, offentlig publisering, kategoriavgrensning, lokal utløpsdato, live-/resultat-/terminlisteadapter, feilformatert providerdata og bevart standardlayout.
- 114 PHP-filer parsersjekket med faktisk PHP-tokenizer. JavaScript, JSON, SVG, maler og eksisterende presentasjonsgrense kontrollert lokalt. PHP-delen av det portable kontrollskriptet ble erstattet av den separate WASM-parsersjekken lokalt; CI kjører originalen med native PHP.
- 12/12 nettleservisninger bestått i Edge/Chromium: radio/nyheter/sport ved 320, 390, 768 og 1440 px. Ingen overflyt, ressursfeil, ødelagte bilder eller JavaScript-feil. Én audio-motor, mobilmeny/Escape og feilhåndtering etter avvist avspilling kontrollert.
- Ny forsideworkflow kjører PHP/kildekontroll, de 38 testene, alle tolv nettleservisningene og staging-ZIP-bygg på den separate grenen/PR-en. Den har ingen deploy-jobb eller produksjonshemmeligheter.

Dette er test av kildekode og isolerte maler. Ingen ny faktisk WordPress-database-/Vipps-/strømtest eller bekreftelse av aktivt produksjonstheme er gjennomført. Radio-LIVE må kobles til tidsstemplet providerstatus på staging; RRLive og publiserte artikler bruker eksisterende lesekontrakter. Når siden lastes, velges prioritering ut fra de tilgjengelige dataene. Nye hendelser i en allerede åpen side krever ny sidevisning; kontinuerlig polling er ikke lagt til i denne første kandidaten.
