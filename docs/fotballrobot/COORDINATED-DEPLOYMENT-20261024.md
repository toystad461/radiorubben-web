# Samordnet utrulling – 24. oktober 2026

Status: **utført og kontrollert i produksjon**. Thomas godkjente eksplisitt «Ja, rull ut koden». Godkjenningen omfattet ni filer, backup, etterkontroll og tilbakeføring; ingen innholdspublisering.

## Levert og bekreftet

- Åtte Web-filer fra #104 (`a9e11eab643e81557948c1a12531f714e6b9b0e1`) og én Studio-fil fra #110 (`b77ac3d01eeff9b93763d6a086cab590f6cde7b2`).
- Manifest SHA-256: `aae582dc433a37f01a42dd7ab9e06bc78e8e8ee51ebace17d9a61522154be48a`. Ingen endring i runtime-pakken under gjennomføringen.
- [Utrulling 40067554321](https://github.com/toystad461/radiorubben-web/actions/runs/40067554321): success. Ferdig kl. 07.46.44 Europe/Oslo. Vedlikehold kl. 07.46.35–07.46.43.
- 67 PHP-filer, 29 Web-suiter, Studio-bildekontraktregresjoner, kombinert nyhetsdesk-/ukesartikkeltest og 60 installasjons-/tilbakeføringskontroller besto før installasjon.
- Fersk kontroll av 47 før-hasher; etter installasjon samsvarte alle 47 etter-hasher. Ni selektive filbytter; ingen main-/fullpakkeinstallasjon.
- Privat kodebackup med originale rettigheter og kontrollsummer: `.radiorubben-deploy/backups/weekly-newsroom-20261024T054632Z-aae582dc433a`. Privat scene: `.radiorubben-deploy/weekly-newsroom-40067554321-72a5ca54a6114e12b29651c6125ae5d1fc82d598`. Ikke eksporter originaldata eller nøkler til GitHub.
- Endelig før-/ettermåling inne i vedlikeholdsvinduet: identiske fingeravtrykk for artikler og vedlegg, metadata, taksonomikoblinger, alle målte rrfr-innstillinger og Studio-sendekø. Per-innstillingshasher er private på serveren.
- Ekte WP/Studio-bro og kølesing besto; ukesruten finnes; bildekatalogen har 21 kontrollerte oppføringer. Autentisert HTTP GET av nyhetsdesken fungerer uten `Cannot redeclare rrni_manifest`.
- [Cache/etterkontroll 40067761547](https://github.com/toystad461/radiorubben-web/actions/runs/40067761547): success. WP-Optimize-sidebuffer tømt via eksisterende `wpo_cache_flush()`; ny runtime-kontroll, redaksjonell likhet og etter-hasher besto.
- Nettleser på vanlig URL etter cachetømming: aktiv ukestil, bilde lastet, 13 kampoverskrifter og ingen horisontal overflyt ved 1348 px. 320/390/1280-kortkontroll er tidligere dokumentert i #104; den er ikke gjentatt på en ny produksjonsartikkel her.
- Innlegg 1398: råtekst, rendret innhold, publiseringsstatus, bilde 812 og endringstidspunkt identiske før/etter. Gammel artikkelstruktur beholdt.
- Eksakt søk etter `results:2026-10-12` gir fortsatt null innlegg. Ingen generering, godkjenning, varsling eller publisering er manuelt utløst. Ingen PR er merget.

## Avvik og faktisk tilbakeføring

Første jobb ble avbrutt før oppstart på grunn av delt GitHub-køgruppe. Deretter stoppet serverforberedelsen før installasjon ved manglende shell-`realpath` og ulik representasjon av hjemmemappens symbolske lenke. Kontrollene bruker nå PHPs realpath på både staging og privat tilstandsmappe.

Forsøk [40067306512](https://github.com/toystad461/radiorubben-web/actions/runs/40067306512) installerte koden og besto runtime-kontrollen, men fant avvik i det samlede fingeravtrykket for rrfr-innstillinger. Artikler, metadata, taksonomier og Studio-sendekø var identiske. Automatisk tilbakeføring gjenopprettet alle ni filbanene, fjernet den nye rrfr_club_results-hendelsen og opphevet vedlikehold. Ingen databaser ble tilbakeført.

Den opprinnelige målingen lagret bare et samlet innstillingsfingeravtrykk; hvilken innstilling som endret seg og årsaken er **ikke fastslått**. En separat oppstartskontroll på gjenopprettet kode var stabil. Førmålingen ble flyttet til etter at vedlikehold var aktivert, og per-innstillingshasher ble lagt til uten å unnta flere innstillinger. Neste installasjon og senere cachekontroll besto full likhet. Dette beviser likhet i de kontrollerte vinduene, ikke at alle bakgrunnsinnstillinger var uendret gjennom hele økten.

## Tilbakeføring og videre arbeid

Bruk den private scenens `scripts/install.php`, manifest og ovennevnte backup under samme scheduler-/deploylåser og kort vedlikehold. `rollback` verifiserer alle originale backuphasher og nekter å overskrive nyere kode. Fire nye Web-filer fjernes; fem eksisterende filer gjenopprettes. Bare ny, leveranseeid `rrfr_club_results` fjernes. Bevar alle database- og redaksjonsendringer. Tilbakeføring bringer også den tidligere nyhetsdeskfeilen tilbake og må funksjonskontrolleres.

Installasjonsworkflowen er nå erstattet av en avgrenset cache-/etterkontroll; den kan ikke installere på nytt. Vanlig preflight kontrollerer nå installerte etter-hasher.

Resultatene for 12.–18. oktober kan klargjøres gjennom `POST /rr-fotballrobot/v1/weekly` med `{"kind":"results","week":"2026-10-12"}`, deretter kilde-/språkkontroll og eksisterende menneskelig godkjenning. Det er ikke utført her. Publisering og endring av publiserte 1398 krever fortsatt separat redaksjonell godkjenning.

Den nye koden registrerer mandag 08.00 Europe/Oslo når klubbautomatikken er aktiv. Eksisterende host-scheduler har en egen hook-allowlist; automatisk kjøring av den nye hooken uten nettsidetrafikk er ikke bevist av denne ni-filers leveransen. Manuell klargjøring via ukesruten er tilgjengelig.
