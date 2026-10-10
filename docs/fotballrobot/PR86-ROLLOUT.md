# PR86 – avgrenset utrulling til 0.10.6

PR86 ligger på produksjonsgrenen fra PR30. Ikke installer fra gammel main og
ikke kjør PR30s historiske installasjon. Bare ti runtimefiler byttes; hele
pluginens øvrige runtime kontrolleres mot forventede SHA-256-hasher. To nye
filer må være fraværende. Historiske manifester endres ikke.

## Ferdig klargjort

- `package-pr86.py` bygger fra Git-objekter, uavhengig av Windows-linjeslutt,
  med et eget manifest. Tester/dokumentasjon installeres ikke i webroten.
- `pr86-preflight.yml` bruker eksisterende SSH-hemmeligheter og verifisert
  vertsnøkkel. Ved push av releasefilene utføres bare syntakskontroll, hashkontroll
  og lesing av kampjobben. Privat staging skrives utenfor webroten. Ingen
  installasjon, WordPress-jobb, køendring eller AI-kall utløses.
- Installer har avgrenset filsett, driftssperre, privat backup/kvittering,
  atomisk filbytte og kontroll etterpå. Rollback sjekker alle backupfiler og
  eierskap før første tilbakeføring; senere kodeendringer overskrives ikke.
- 47 isolerte installasjonskontroller prøver pakkeavvik, produksjonsavvik,
  hele etterbildet, nye filer, backup, gjentatt rollback og samtidig kodeendring.

## Selve utrullingen krever separat godkjenning

1. Krev grønne regresjonstester og grønn **PR86 read-only production preflight**
   på samme commit som skal installeres. Avvik i en eneste runtimefil stopper
   utrulling. Undersøk avvik; ikke regenerer før-hasher fra en ukjent serverkode
   for å få kontrollen grønn. Ny push etter grønn kontroll krever ny kontroll.
2. Kontroller kamp 8985501 på nytt: jobb, `_rrfr_ai_match`, slug, eventuell
   draft/publisert artikkel og redaktørtekst. Ikke replay eller slett jobben.
   Preflight skriver bare status/tider, aldri artikkeltekst eller avstemningsdata.
3. Bruk den private stagingbanen fra grønn preflight. På eksisterende SSH-vert:

   ```sh
   release=<godkjent-full-commit-sha>
   stage="$HOME/.radiorubben-deploy/pr86/$release"
   backup="$HOME/.radiorubben-deploy/backups/pr86-$release"
   bash "$stage/scripts/deploy-pr86.sh" "$stage" "$backup" install
   ```

   Scriptet kontrollerer før-hashene igjen under felles utrullingslås, tar
   en ny backup, installerer og kontrollerer hele runtime etterpå. Feil etter
   installasjon utløser tilbakeføring. Kun kode tilbakeføres; artikler og kø
   skal aldri rulles tilbake fra en gammel databasekopi.
4. Kontroller aktiv pluginversjon 0.10.6, robotsiden og godkjenningsflaten.
   Kontroller at ingen avstemning ble endret og at eksisterende utkast er beholdt.
5. Aktiver separat kjøring uten nettsidebesøk. **En merge av denne PR-en aktiverer
   ikke scheduler.** GitHubs planlagte workflow krever defaultgrenen og variabelen
   `RRFR_SCHEDULER_ENABLED=true` og `RRFR_SCHEDULER_REF` satt til godkjent full
   commit-SHA; denne PR-en er stablet på en annen gren. Runneren hentes fra den
   uforanderlige SHA-en, slik at gammel main-kode ikke kjøres.
   Bruk derfor enten en separat, godkjent overføring av bare scheduler-workflowen til
   defaultgrenen eller vertens eksisterende cron-tjeneste. Ikke merge gammel
   main-runtime til produksjon. Verifiser tilgjengelig ekstern cron før du lover
   automatisk drift; tilgjengelig `flock`/`timeout` alene beviser ikke cron.
   Vertskjøringen skal peke på det godkjente, private scriptet:

   ```sh
   flock -n "$HOME/.radiorubben-deploy/fotballrobot-scheduler.lock" \
     timeout 480 /usr/local/bin/wp --path=/run/webroots/r1417157 \
     eval-file "$stage/scripts/fotballrobot-cron.php"
   ```

   Registrer hvert femte minutt i den faktisk tilgjengelige scheduler-tjenesten.
   Bevar eksisterende cronoppføringer og ta konfigurasjonsbackup. Feilstatus må
   gi driftsvarsling; ikke send kildedata/artikkeltekst til offentlige logger.
   Ingen slik aktivering er utført ved klargjøringen.
6. Verifiser fersk `rrfr_match_followup_runner` med `transport=host-cli` fra
   ekstern kjøring uten HTTP-besøk. Kontroller kø, nøyaktig ett utkast og manuell
   publiseringssperre. Jobben kan bruke betalte modellkall etter godkjent aktivering.

## Tilbakeføring

Stopp den nye eksterne scheduler først; behold øvrige cronoppføringer.
Bruk samme staging/backup og `rollback` i stedet for `install` i kommandoen
over. Scriptet nekter tilbakeføring over senere kodeendringer. Behold nye
jobbkontrollpunkter og artikler; de slettes ikke. Kontroller aktiv 0.10.5,
webflaten og eksisterende kø. Gamle 0.10.5 har oppdagelseshullet som PR86 retter.

## Status

Klargjøring er ikke utrulling. Ny lesende kontroll 10.10 kl. 09.44 viste at
8985501 nå er `done/review`, med eksisterende utkast 1303,
`rubben-kamp-8985501`, sist endret kl. 09.43.55. Kampen skal ikke kjøres om.
Dette beviser at den gamle køen fullførte etter morgenens manuelle arkivering;
det tidligere påviste automatiske oppdagelseshullet består. Resultatet av SSH-preflight og
regresjonstester dokumenteres i PR-en. Den lesende SSH-kontrollen på c6003fd
bestod 10.10: samtlige 37 runtimebaner (inkludert to fraværende nye filer)
samsvarte med manifestet. Pakken, PHP på verten og nødvendige kjøreverktøy ble
kontrollert uten installasjon. Senere endringer må også ha grønn kontroll.
Faktisk scheduleraktivering, trafikkuavhengig
kjøring og kontroll av et ekte AI-utkast kan først bevises etter godkjent utrulling.
