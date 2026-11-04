# Fotballrobot: scheduler-kontroll 24.10.2026

Avgrensning: lesekontroll, nødvendig kodeendring, syntetiske tester og PR.
Ingen produksjonsutrulling, endring av GitHub-variabler, server-cron eller
kjøring av ekte artikkeljobber som test er utført i dette arbeidet.

## Bekreftet drift og kildegrunnlag

- Fersk `main`: `5169765446b75f5c983695940241985beda2f7ad`.
- Siste observerte scheduler-kjøring:
  [40057393206](https://github.com/toystad461/radiorubben-web/actions/runs/40057393206),
  startet 24.10 kl. 03:13:04 UTC (05:13:04 Europe/Oslo), fullført med success.
  Jobbloggen viser `OK: Fotballrobot scheduler; events=1`, fungerende CLI-bootstrap
  og ingen venting av betydning på host-låsen.
- Forrige kjøring:
  [40042509862](https://github.com/toystad461/radiorubben-web/actions/runs/40042509862),
  startet 23.10 kl. 23:57:39 UTC; loggen viser `events=0` og success.
- Begge logger bekrefter faktisk kildepin
  `f31b4765f6ec8020d97eb6779e37b344002cb16d`. Det er denne eldre runneren
  som fortsatt brukes, selv etter nyere selektive plugin-utrullinger.
- WordPress-option `rrfr_match_followup_runner` var
  `{"at":1792811618,"transport":"host-cli","events":1}`.
  Tidspunktet er 24.10 kl. 03:13:38 UTC / 05:13:38 Oslo og samsvarer med loggen.
- De 30 siste schedule-resultatene inneholdt 28 fotballkjøringer, alle success.
  Avstanden mellom de ti siste fotballstartene var 2 t 23 min–4 t 54 min,
  avrundet. I hele utvalget var største avstand 5 t 33 min.
  Dette er observerte startintervaller, ikke målt behandlingstid for en kamp.
- Søk i de 50 siste kjøringene på tvers av hendelsestyper viste heller ingen
  nyere scheduler-kjøring. Det beviser ikke årsaken til uteblitte triggere.
- `rrfr_match_jobs.enabled=1` og klubbautomatikken var aktivert.
  WP-cron inneholdt `rrfr_club_weekly` 25.10 kl. 17:00 UTC (18:00 Oslo)
  og `rrfr_club_results` 26.10 kl. 07:00 UTC (08:00 Oslo).
- Installert `weekly-coverage.php` inneholder hooken `rrfr_club_results` og
  mandagsplanen. Lesekontrollen er funksjonsbelegg, ikke en byteeksakt hashkontroll.
  Dagens installerte ukesfunksjon er dokumentert i
  [den selektive utrullingskvitteringen](https://github.com/toystad461/radiorubben-web/blob/3097bd1bf234f1df90eb674681036ab8b27354e0/docs/fotballrobot/COORDINATED-DEPLOYMENT-20261024.md).

## Konklusjon og avgrenset rettelse

Den bekreftede kodefeilen er at den eksterne runneren ikke tillater
`rrfr_club_results`. Dermed blir forfalte resultatoppsummeringer stående utenfor
denne kjøreveien. Vanlig WordPress-cron kan fortsatt behandle dem; funnet er
ikke bevis på at alle ukesjobber eller hele roboten har stoppet.

PR-en fører `scripts/fotballrobot-cron.php` inn på en selvstendig gren fra main,
med eksakt gammel runner som utgangspunkt. Kun to atferdsendringer gjøres:

1. Legg til `rrfr_club_results` i tillatelseslisten.
2. Kontroller at hooken har en registrert behandler før hendelsen fjernes eller
   legges til på nytt. Ved inkompatibel plugin stoppes kjøringen med synlig feil,
   og hendelsen blir liggende i køen.

Eksisterende aktiveringskrav, kampoppfølging, øvrige tillatte jobber, argumenter,
12-jobbersgrense, tidsbudsjett og heartbeat-format beholdes. Endringen omfatter
ikke produksjonsplugin, publiseringsgodkjenning, kildetilgang eller nye AI-kall
utover den allerede planlagte ukesfunksjonen når runneren senere aktiveres.

Heartbeat-alderen skyldes at siste observerte eksterne kjøring er gammel.
Loggene viser ikke en mislykket heartbeat-skriving eller langvarig host-lås.
Årsaken til manglende GitHub-start hver femte minutt er **ikke fastslått**.
[GitHubs dokumentasjon](https://docs.github.com/en/actions/how-tos/troubleshoot-workflows)
beskriver at schedule-hendelser kan forsinkes og falle bort ved høy belastning.
Dette er en mulig forklaring, ikke bekreftet rotårsak for dette repoet.
Kodeendringen lover derfor ikke presis kjøring hver femte minutt.

## Tester og begrensninger

Kjør fra repo-roten i et isolert utviklings-/CI-miljø:

```sh
php -l scripts/fotballrobot-cron.php
php -l scripts/tests/fotballrobot-cron.php
php scripts/tests/fotballrobot-cron.php
```

Ni syntetiske scenarioer dekker ukesresultater, gjentatt kjøring, fremtidige og
uvedkommende jobber, eksisterende fotballhooker, manglende behandler, deaktivert
automatikk, mislykket reservasjon, jobbgrense, tidsbudsjett og heartbeat/feilkvittering.
CI bruker PHP 8.2 og 8.3 og verifiserer også at testen oppdager mangelen i den
gamle produksjonspinnen. Ingen ekte WordPress-installasjon, kilde-API eller
artikkelproduksjon brukes i testene. Grønn CI er ikke en produksjonskvittering.

Den nye test-workflowen har kun lesetilgang til repoet og ingen deploy-steg.
Eksisterende operativ scheduler-workflow er uendret. PR-en endrer ingen stier
som utløser WordPress-, poll-, bilde- eller theme-deploy.

## Konkret neste produksjonssteg, etter separat godkjenning

1. Kontroller eksakt godkjent PR-head, grønne PHP-jobber og gjeldende main/workflow.
   Ta ferske serverhasher av relevante plugin-filer og avstem mot installert
   ukesrelease; kontroller registrert resultathook, aktivering og aktuelle jobber.
2. Bevar gammel runner-pin og kopi/hash av eventuell fil som erstattes, privat.
   Sett kun `RRFR_SCHEDULER_REF` til det godkjente, uforanderlige commit-SHA-et.
   Dagens workflow henter og overfører bare runnerfilen fra dette SHA-et.
   **Ikke installer hele denne grenen som WordPress-plugin.**
3. Kontroller neste ordinære kjøring: riktig SHA i loggen, fungerende CLI,
   OK-kvittering og nyere heartbeat. Ikke tving mandagens resultatjobb frem som test.
   Denne kjøringen kan behandle reelle forfalte jobber og krever driftsgodkjenningen.
4. Ved feil: tilbakefør kun runner-pinnen til
   `f31b4765f6ec8020d97eb6779e37b344002cb16d`. Ikke gjenopprett database eller
   slett nye køelementer/artikler. Den gamle pinnen gjeninnfører begrensningen
   for ukesresultater, så de må følges opp separat.

Deretter anbefales et eget avgrenset driftssteg: undersøk host-/VPS-timer for
jevn oppstart og overvåk alder på siste kjøring. Behold én ansvarlig ekstern
starter og eksisterende låser; ikke opprett en ekstra konkurrerende kjørevei.
Servertilgang, timeroppsett og dette valget er ikke utført eller verifisert her.
