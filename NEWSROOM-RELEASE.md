# Samlet nyhetsdesk

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
