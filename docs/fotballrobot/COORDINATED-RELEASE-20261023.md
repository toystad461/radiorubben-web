# Samordnet Web #104 + Studio #110

Arbeidsområder: Teknisk Produksjon / System & Kvalitet / Lokalt Engasjement.
Status: klargjort kandidat, ikke godkjent for produksjon og ikke utrullet.

## Fastlåst omfang

- Web #104: `a9e11eab643e81557948c1a12531f714e6b9b0e1`, base `6c36802ee04879f7e79d319911902a03a5157c04`.
- Studio #110: `b77ac3d01eeff9b93763d6a086cab590f6cde7b2`, base `96ff332d9b26f07b3be68c1d9cf2c9e58b6a7c60`.
- Bildekontrakt fra Web main `5169765446b75f5c983695940241985beda2f7ad`.
- Nøyaktig åtte Web-filer og `studio-private/app/news-images.php`; ingen annen runtime-endring.
- Manifest: `COORDINATED-RELEASE-20261023.json`, 9 installasjonsfiler og 47 kontrollerte filbaner.
- Manifest SHA-256: `aae582dc433a37f01a42dd7ab9e06bc78e8e8ee51ebace17d9a61522154be48a`.

Kandidaten rulles ut selektivt fra disse kildecommitene. Verken main, hele PR-grenen eller Studios fullpakke skal installeres. Nye deployskript ligger i `scripts/weekly-newsroom`; historiske releasefiler er urørt. Studio #111 endrer teststrøm og overlapper ikke runtime-filene her. Kun statusdokumentet overlapper; den PR-en tas ikke med. Begge opprinnelige PR-er står åpne utkast.

## Bekreftet ved ny kontroll 23. oktober

- Web CI [39854255631](https://github.com/toystad461/radiorubben-web/actions/runs/39854255631): 67 PHP-filer og 29 testsuiter består; loggene viser faktisk kjøring av `weekly-publication.php`.
- Studio CI [39853308368](https://github.com/toystad461/radiorubben-studio/actions/runs/39853308368): PHP 8.2/8.4 og mobilkontroller success, deploy skipped. PR-beskrivelsens «full CI gjenstår» er foreldet.
- Lokalt gjentatt: 67 PHP-filer / 29 Web-suiter, Studio-bilder og begge kontraktregresjoner, PHP WASM 8.5.10.
- Ny kombinert test laster bildekontrakten først, hele Studio-nyhetsmodulen deretter og ekte ukesartikkel-/godkjenningskode med syntetiske WordPress- og modellsvar. Kontrollerer at kølesing bevarer artikkel, bilde og godkjenningsdata.
- Installasjonsprøven består 60 kontroller og kontrollerer drift, skadet pakke, privat backup, eksakte etter-hasher, tilbakeføring, gjentakelse og samtidige kodeendringer. Ingen produksjonsdata brukes i tester.
- Autentisert produksjonslesing: WordPress 7.1.3, PHP 8.2.35, aktivt tema Radio Rubben Next 2.0.0-rc.5, Fotballrobot 0.10.8 og rr-news-images 1.0.0.
- GET newsroom feiler fremdeles med HTTP 500 og konkret `Cannot redeclare rrni_manifest()` mellom WP-kontrakten og `studio-private/app/news-images-contract.php`.
- Autentisert GET av bildekatalogen består: 21 medier og forventet registerhash `0f090e476d8042339e1dab75b7653b34548ff24496294bb38908b3db0fabe0da`; endepunktet verifiserer filer, mediekobling, dimensjoner og KI-merking. Ingen bildeimport ble kjørt.
- Lesende databasesøk etter den eksakte ukesnøkkelen `results:2026-10-12` gir 0 treff på tvers av innleggsstatuser. Resultatoppsummeringen for 12.–18. oktober er derfor fortsatt ikke registrert i denne flyten.
- Innlegg 1398 er publisert, sist endret 22.10.2026 kl. 22.41.32 Europe/Oslo, har 13 kamper og gammel utforming. Ingen endring er utført.

Visuell kortkontroll ved 320/390/1280 px er dokumentert i #104, men er ikke gjentatt i denne runden: lokal Chromium var ikke installert, og nettlesernedlastingen feilet. Aktivt produksjonstema og autentisert redigeringsløp må fortsatt kontrolleres.

Grønn CI erstatter ikke faktisk produksjonskontroll. Den samordnede workflowen kjører tester og en lesende SSH-kontroll via eksisterende `WP_DEPLOY_SSH_KEY` / `WP_DEPLOY_KNOWN_HOSTS`. Den laster ingen filer opp til serveren, starter ikke WordPress og har ingen deployjobb. Workflowen er foreløpig bare klargjort lokalt. Automatisk godkjenningskontroll avviste `git push` av forberedelsesgrenen: den tolket brukerens tillatelse som forberedelse og inspeksjon, ikke opplasting av kildekode til GitHub. Ingen workflow ble kjørt, og ferske serverhasher er derfor ikke verifisert. Neste nødvendige godkjenning gjelder opplasting av denne avgrensede grenen og kjøring av den lesende kontrollen, uten produksjonsutrulling.

## Gjennomføring etter uttrykkelig godkjenning

1. Kontroller på nytt PR-heads, relevante CI-resultater og gjeldende serverhasher. Stopp ved drift; ikke endre før-hash for å tvinge en installasjon gjennom.
2. Bygg med `python3 scripts/weekly-newsroom/prepare.py <studio-klon> <ny-pakkemappe>`. Verifiser fastlåst manifest og pakke lokalt. Bruk eksisterende personlige Uniweb-SSH-konto og verifisert vertsnøkkel; aldri kopier nøkler eller private data til GitHub.
3. Last bare pakken til en ny privat undermappe i eksisterende `.radiorubben-deploy`. Kjør serverens PHP-syntakskontroll før installasjon.
4. Installerprogrammet tar Fotballrobotens scheduler-lås, WordPress-deploylåsen og Studios deploylås. Eksisterende vedlikeholdsmodus eller tidligere backup med samme navn stopper forsøket.
5. Lag privat kodebackup og kvittering med opprinnelige rettigheter og før/etter-hasher. Ta separate fingeravtrykk av artikler, medier, metadata, taksonomikoblinger, redaksjonelle valg og Studio-sendekø; ikke eksporter innholdet.
6. Bytt ni filer med atomisk filbytte under kort vedlikehold. Dette er atomisk per fil, med samlet tilbakeføring ved feil; det er ikke en database-transaksjon. Ingen databasemigrering eller omskriving av innlegg utføres.
7. Krev frisk WP/Studio-kø, 21 gyldige bildereferanser, aktiv ukesrute, fortsatt publisert 1398, identiske redaksjonelle fingeravtrykk og alle 47 etter-hasher. Krev deretter HTTP-kontroll av forside og 1398 samt autentisert GET av nyhetskøen og visuell kontroll i aktivt tema.
8. Kvitter bare for fullført utrulling når alle kontrollene er bestått. Kontroller web-PHP/OPcache separat dersom CLI-hashene er riktige men HTTP viser gammel kode.

`deploy.sh` er klargjort, men ingen workflow kjører det. Det krever `RR_WEEKLY_APPROVED_MANIFEST` med den eksakte godkjente manifesthashen. Variabelen er en teknisk sperre; den erstatter ikke Thomas sin godkjenning.

## Tilbakeføring

Ved feil gjenopprettes bare de ni eide filbanene fra den nye private backupen; fire nye Web-filer fjernes. Hver backuphash og gjeldende målfils hash kontrolleres før tilbakeføring. En nyere kodeendring blokkerer overskriving og beholder vedlikehold til avklaring. Ingen database, mediebibliotek eller sendekø rulles tilbake, slik at nytt redaksjonelt arbeid ikke slettes. Kontroller opprinnelige hasher og nettstedets tilstand etterpå. Tilbakeføring til før-koden bringer også den kjente nyhetsdeskfeilen tilbake; feilrettingen skal derfor ikke omtales som levert dersom rollback er nødvendig.

PR104 registrerer `rrfr_club_results` mandag 08.00 Europe/Oslo når klubbautomatikken allerede er aktiv. Søndagens eksisterende oversikt beholdes. Ny cronregistrering er forventet kodeeffekt og må kontrolleres separat. Dersom utrullingen tilbakeføres etter registrering, fjernes bare en ny, tom `rrfr_club_results`-hendelse dersom før/etter-kontroll bekrefter at den er opprettet av denne leveransen; eksisterende cronhistorikk skal ikke erstattes.

## Redaksjonelt etterarbeid, separat fra kodeutrulling

Etter godkjent og verifisert utrulling kan det eksisterende endepunktet klargjøre resultatene for 12.–18. oktober: `POST /rr-fotballrobot/v1/weekly` med `{"kind":"results","week":"2026-10-12"}`. Kontroller først om samme uke allerede finnes; et eksisterende innlegg returneres uendret. Utkastet skal gå gjennom kilde-/språkkontroll og den eksisterende nyhetsdesken. Ingen publisering eller godkjenning er del av installasjon eller funksjonstest.

Innlegg 1398 skal fortsatt ha sin nåværende tekst, bilder, URL og godkjenning. Ny utforming på dette publiserte innlegget krever et eget kontrollert endringsforslag og særskilt redaksjonell godkjenning. Alle 13 kamper og lagret kildegrunnlag må bevares. Ikke legg til logoer som mangler i det kontrollerte faktagrunnlaget ved å endre råmetadata.
