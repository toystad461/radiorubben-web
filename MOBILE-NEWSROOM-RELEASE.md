# Selektiv mobilforbedring – 05.10.2026

Kilde: Studio `e977efaf9a0e2e51abc9ede9513cd7a37f04b988` (PR 25),
WordPress `29e49e75b7890dd885050b4ad83d97ad266288fa` (PR 52, Fotballrobot 0.10.4).
Studio CI 37243066275 og 37243068279 er grønne (PHP 8.2/8.4 samt mobiltester).
WordPress CI 37243108895 er grønn, inkludert hele Fotballrobot-suiten.

Brukeren ønsker færre skjermbytter på mobil. Saken, bildet, endringsønsket og
manuell godkjenning vises sammen. Bekreftet avgjørelse åpner neste ferdige sak.
Kø, meny og sekundære valg er foldet. Feil beholder tekst; uavklart nettverksfeil
sperrer ny innsending frem til status er hentet. Hver ny sak krever ny bekreftelse.

## Avgrensning

Bare seks Studio-filer og fire pluginfiler i `scripts/mobile-newsroom-release.json`
installeres. Manifestet har SHA-256 før og etter, og den transporterte Studio-
filpakken er knyttet til eksakt commit. Studio er fortsatt selektivt installert;
ikke deploy hele grenen eller standardpakken. Privat konfigurasjon, brukere,
hemmeligheter, sendeliste, cache og historikk endres ikke.

## Utrulling og tilbakeføring

Workflow `mobile-newsroom-release.yml` kjører isolerte tester og verifiserer
pakkehasher før SSH. Eksisterende godkjent deploynøkkel og strict known-hosts brukes.
Før installasjon kontrolleres alle levende filhasher og pluginversjon 0.10.3.
Drift stopper før overskriving. Sikkerhetskopi ligger privat i
`$HOME/.radiorubben-deploy/backups/mobile-newsroom-<release-sha>`.
Tiril-sakens publiserte tekst, kontrollgrunnlag, hovedbilde og ventende forslag
får et privat før-/etter-fingeravtrykk. Ingen sak godkjennes og ingen e-post sendes.

Tilbakeføring bruker samme manifest og kodekopi:
`php <stage>/scripts/mobile-newsroom-install.php <stage> <backup> rollback`.
Installasjon/postflight-feil utløser automatisk tilbakeføring under samme lås.
Nye filer fjernes bare hvis de inngår i dette manifestet; tidligere filer gjenopprettes.
Stage: `$HOME/.radiorubben-deploy/mobile-newsroom-transport` er ikke brukt;
faktisk stage er `$HOME/.radiorubben-deploy/mobile-newsroom/<release-sha>`.

Produksjonsresultatet føres etter fullført jobb. Mobiltestene bruker isolerte
fiktive saker på 375/390/800/1280 px. Fysisk iPhone og innlogget produksjonsflyt
med en reell publisering er ikke brukt som test.
