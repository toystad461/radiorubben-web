# Samlet nyhetsdesk

Aktiv: Fotballrobot 0.10.1 og Studio-runtime `5fdee8efd3648b9cfb32f20f2113cb5a1b771d55`.
Siste release 37237964444 er grønn; autentisert HTTP bekrefter fungerende
Studio-kø i WordPress. Tidligere delreleaser er dokumentert nedenfor.

Kilderepo for Studio er `toystad461/radiorubben-studio`, PR #24,
commit `09ead0c10605e668732c1dea3049c4a6bcb779cf`. Full CI på PHP 8.2/8.4
passerte i kjøring 37235892971. Fotballrobot-runtime er commit
`4d4143888a3b53b471335aff9f663bf323f345f9` i PR #50; full integrasjons-CI
passerte i 37235894293. Ingen merge eller hel-repo-deploy.

`scripts/release-artifacts/studio-09ead0c.json` er kun en engangs
transportpakke fra denne testede Studio-committen, med samme omskriving av
offentlige include-baner som Studio sin `scripts/package.php`. Den er ikke
et nytt vedlikeholdspunkt for Studio. Filopplasting i Uniweb-browseren ga
ikke en fungerende filvelger; eksisterende SSH-deploy brukes til transport.

Manifestet låser ni Studio-filer og seks Fotballrobot-filer, deres eksakte
før-/etter-hasher, kildecommitter og CI-kjøringer. Installasjonen avviser
kodeavvik, eksisterende nye filer og symlenker. Privat backup og tilbakeføring
ligger under serverens `.radiorubben-deploy/backups/newsroom-<release-sha>`.
Kun de angitte kodefilene endres ved installasjon. Konfigurasjon, innhold,
historikk, brukere og øvrige nøkler inngår ikke i kodepakken.

Separat forbindelsesreparasjon: verifiser først eksisterende Studio-nøkkel.
Bare hvis den feiler, opprett en dedikert Studio-programnøkkel for samme
allerede konfigurerte konto. Bevar alle andre applikasjonsnøkler, inkludert
WPVibe. Sikkerhetskopier privat konfigurasjon og skriv aldri hemmeligheter til
Git, logg eller klient. Feilet kontroll ruller forbindelsesendringen tilbake.

Aktivering skrur på klargjøring av inntil åtte nye kilder per Oslo-døgn og
samlevarsler. Tidligere varslede, uendrede forslag markeres som kjent. Ingen
ekte artikkel publiseres og ingen test-e-post sendes av release-skriptene.
Kjøringene og live UI må kontrolleres før endringen rapporteres som aktiv.

Ved tilbakeføring: stopp først de to `rrfr_newsroom_*`-cron-jobbene og sett
`rrfr_newsroom_enabled` av. Bruk `newsroom-install.php <stage> <backup> rollback`
for de eksakte kodefilene. En eventuell ny Studio-programnøkkel kan tilbakeføres
med UUID-en i privat `private-connection-backup/created-key.json`; ikke berør
andre nøkler. Eksisterende innhold og historikk skal ikke rulles tilbake.

## Bekreftet aktivering

- Release `ef7808506f3c31837b8717198751ceac91eee385`, kjøring 37236445470:
  alle 15 filer kontrollert før/etter, privat backup, fungerende Studio-worker,
  nyhetsbro med 12 WordPress-kort, reparert privat forbindelse og aktiverte jobber.
- Oppfølging `9b92a8808fb1738da981717d27b4c2804e7bf264`, kjøring 37237084802:
  Studio-runtime `0e2cd7273a641267213ee977179dd368723b9a0f`. Bevar teksten og
  originalen når kontrollsvaret er ufullstendig; eksplisitte segmentindekser og
  kompakt sakskø. Ny forbindelse/oppsett ble ikke opprettet på nytt.
- Separat verifisering løste kun den kjente gamle kladdoverføringen
  `37115a841ef21a07` etter all-status slug-søk uten treff. Ingen artikkel ble
  opprettet eller publisert ved gjenopprettingen.
- Live desk er åpnet med Microsoft-innlogging. Ekte NRK-original og egen tekst
  er lest i flaten; manglende belegg sperrer godkjenning. Retting og ny kontroll
  er kjørt fra desken. Ingen test-e-post eller reell publisering i kontrollen.

Første faktiske samlevarsel må observeres i ordinær drift; lokal transporttest
bruker mock. Uklare sendinger gjentas ikke automatisk. Eldre avvik i køen er
synlige og krever fortsatt redaksjonell oppfølging.

## Kildebelegg og kjøring i WordPress

Studio-commit `5fdee8efd3648b9cfb32f20f2113cb5a1b771d55` lar kontrolløren
peke til nummererte originalavsnitt. Serveren henter belegg direkte fra
snapshotet; kontrolløren kan ikke oversette eller endre beleggsteksten.
CI 37237498827/37237495149 og release 37237550629 passerte.
NRK-utkastet `8140cd54c5b6e21d` er kontrollert og står til godkjenning med
manuell godkjenningsboks urørt. Den nye flaten er kontrollert i nettleseren.

Fotballrobot 0.10.1 bruker de samme private Studio-modulene direkte i
WordPress-cron, uten avhengighet til `proc_open` eller separat PHP-prosess.
Bakgrunnen var feilmelding fra ordinær planlagt klargjøring mens CLI-kjøring
fungerte. Arbeidet utvider ikke HTTP-tilgang eller filrettigheter. Worker og
publiseringsgater beholdes; siste vellykkede kjøring registreres i status.
Runtime: `a253cfa3c41aa175cdceebc6ce46e8c5cd4d7b76`, full CI
37237908379/37237905651. Release: `05eca254c7ad3d071f021925bf94de5fd8e1342b`.

Bekreftet ordinær bakgrunnskjøring 04.10.2026 kl. 21:59:09 UTC / 23:59:09 Oslo:
`rrfr_newsroom_last_prepare` returnerte `state: prepared`. WordPress sin cron-test
bekreftet fungerende oppstart (HTTP 200); `DISABLE_WP_CRON` er ikke satt.
Dette er en faktisk planlagt klargjøring gjennom WordPress, i tillegg til
manuell UI-kontroll. Ingen automatisk artikkelpublisering er aktivert.
