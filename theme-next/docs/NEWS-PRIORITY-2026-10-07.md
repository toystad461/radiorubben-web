# Nyheter høyere på forsiden — GitHub/Codex-løp

Thomas ba om publisering og presiserte at GitHub og Codex skal følges.
Denne avgrensede endringen skal ikke legges direkte inn via WordPress-editor,
WPVibe-snurrebiter eller en erstatning av hele temaet utenfor Git.

## Grunnlag og avgrensning

Fersk main er `9006e82e1ebc91e2a128cf2d3e263470e16db8e0`. Den mangler
spillerwidget-koblingen som finnes på dagens offentlige forside.
Endringen bygges derfor på den dokumenterte Next-produksjonsgrenen fra PR #64:
`release/next-production-20261006`, commit `f7cce8c4129e68361e65e86928aca71c24140026`.
`front-page.php` i denne grenen samsvarer tekstlig med lest produksjonsfil.
Dette er ikke en fersk SHA-256-kontroll av serverfilens råbytes.

PR #65 (`fix/mobile-sticky-header-20261007`) er separat og tas ikke inn her.
Ingen endring i Studio PR #47, innlogging, robot, kampmotor, stemmegivning eller
publiserte innlegg og fremhevede bilder. OneDrive-filer og mapper er urørt.

Tre temafiler utgjør selve endringen:
- `front-page.php`: kompakt eksisterende radio først, deretter Siste nytt,
  medlemsfelt, separat sport, eksisterende spillerwidget og øvrig innhold.
- `template-parts/home/news-priority.php`: kun publiserte, offentlige innlegg.
  Nyheter inkluderer `latest-updates` og `lokale_nyheter` med underkategorier,
  men utelukker Sport/Fotball med alle underkategorier. Dobbeltkategoriserte
  innlegg vises i sport. Leder- og programinnlegg blandes ikke inn i nyhetene.
  Én hovedsak og to mindre saker, faktiske publiseringstider, én illustrasjon.
- `assets/css/news-priority.css`: responsiv, avgrenset til klassisk forside;
  overskrift før bilde på hovedsaken, én kolonne på mobil, ingen ny lydmotor.

Andre eksisterende forsidevalg (innhold, arkiv, passord, tre univers) er urørt.
Alle eksisterende hooks for spillerwidget, RSS, vær, sponsorer og ekstra innhold
beholdes. Sportsartiklenes data og bilder oppdateres ikke av visningen.

## Utførte og gjenstående kontroller

Lokalt: PHP 8.4.23 syntakskontroll og 24 isolerte kontraktkontroller bestått.
Disse verifiserer query-argumenter, utelukkelser, escaping, tom liste, datoer,
rekkefølge, manglende bilde og bevarte forsidevalg. Queryene er stubbet; dette
er ikke en WordPress-databaseintegrasjonstest.

Ny GitHub-workflow kjører de samme kontraktene, eksisterende temakontroller
og åtte skjermvarianter: 320/393/768/1280 px med normal og doblet skrift.
Skjermtesten bruker syntetiske PHP-fixtures og reelle stilark fra grenen,
med eksternt nettverk avslått i nettleseren. Ekte WordPress, iPhone/Safari,
cache og avspilling er ikke bevist av denne testen. Faktisk CI-status føres
på PR-en etter kjøring; dette dokumentet alene er ikke et grønt testbevis.

## Publisering — sperre før noen serverendring

Eksisterende `scripts/deploy-wordpress.sh` på main retter seg mot det gamle
`themes/radio-rubben-wordpress-v1` og flere pluginmapper. Det er IKKE en trygg
publiseringsvei for disse tre Next-filene. Next-workflowen tester og pakker,
men publiserer ikke temaet. Ikke bruk gammel apply som en snarvei, ikke endre
aktivering, og ikke erstatt hele produksjonsgrenen med main.

Codex-oppfølging på denne PR-en:
1. Gjennomgå diff og nye CI-resultater mot eksakt head. Kontroller også faktisk
   WordPress med Sport-barn og et innlegg i både Nyheter og Sport.
2. Kartlegg støttet selektiv GitHub-utrulling til det aktive Next-temaet. Bevar
   eksisterende godkjenningsgrenser, SSH-verifikasjon, private sikkerhetskopier
   og driftkontroll. Bruk bare de tre temafilene nevnt over.
3. Les ferske før-hasher og kontroller alle avhengigheter; bevar eventuelle
   nyere produksjonsendringer og PR #65. Ta privat backup og test tilbakeføring.
4. Etter autorisert utrulling: verifiser etter-hasher, offentlig HTML og mobil/PC,
   sportseparasjon, uendrede innlegg/bilder, spillerwidget, radio/pausevisning,
   og eventuell gjenbygging av WP-Optimize sin kombinerte CSS.
5. Registrer eksakt commit, kjøring og resultat. Først da kan endringen kalles
   publisert. Ingen ny redaksjonell sak skal godkjennes/publiseres i denne testen.

Status ved opprettelse: kode/PR-forberedelse, ingen merge eller utrulling.
