# Spilleroppfølging – kildeinnsamling på VPS

Arbeidsområder: System & Kvalitet, Teknisk Produksjon og Lokalt Engasjement.
Leveranse 3. november 2026. **WordPress-delen er installert og server-/REST-verifisert.
VPS-komponenten er implementert og isolert testet, men ikke installert eller verifisert i drift.**

## Avstemt eksisterende løsning

- Produksjonens `player-monitor` er lest 03.11.2026: engine
  `radio-rubben-fotballrobot`, API-versjon 0.10.4, automatic_proposals=true,
  publication=manual_approval_required, alle tre nødvendige features og planlagt
  next_check. Alle tolv profiler var enabled. Ingen uttømmende spillerliste i koden.
- Aktiv pluginhovedfil oppga **0.10.8** før endringen; installert versjon er nå **0.10.9**. API-versjon og pluginversjon er forskjellige.
- Hovedfil, players.php, player-monitor.php, player-candidates.php og media-sources.php
  er lest gjennom WPVibe og innholdssammenlignet med
  `10e9684d6061deccc9bdd5ac39d3cf8251f4bfc4` på eksisterende
  `feature/weekly-match-stories-20261024`. Sammenligningen ignorerer avsluttende
  linjeskift fra leseverktøyet; dette er **ikke en byteeksakt serverhash-attest**.
- Etterfølgende read-only SSH-preflight gjennom eksisterende GitHub-deploy bekreftet
  byteeksakte SHA256-hasher for de fire berørte eksisterende filene mot baseline,
  aktiv plugin 0.10.8 og riktig home-adresse. Workflow: 41682019922.
- Main `559a65c8e85004b7365af4f2a7d2fa5269172e23` mangler komplett Fotballrobot.
  Derfor avgrenses PR-en mot eksisterende plugin-gren. Ikke merge/deploy hele kjeden.
- NFF-kontroll: Players::collect/tick og MatchFollowup i WordPress. Observerte
  WP-Cron-hooks inkluderer rrfr_profiles_tick, rrfr_players_tick,
  rrfr_match_followup, rrfr_newsroom_prepare og rrfr_newsroom_digest.
  Den eksisterende GitHub-scheduleren starter andre fotballjobber via WP-hostens SSH.
  Den er ikke den nye VPS-innsamleren og blir ikke endret.
- Kilder ligger i eksisterende PlayerMonitor-innboks; PlayerReview/Newsroom eier
  skriving, kontroll og manuell tekstgodkjenning. Kandidater ligger i
  PlayerCandidates og blir pending. Ingen ny publiseringsmotor.
- ChatGPT-oppgaven **Finn spillere og mediesaker til Fotballroboten**,
  `6aac6cc9f4e88191a79aa38a966df0c2`, var aktiv ved kontroll. Den er **ikke stoppet**.
- VPS-tjenesteinventar kunne ikke leses i denne økten. `ssh rubben-test` feiler
  fordi aliaset ikke er konfigurert/oppløselig her. Tidligere begrenset filgateway
  er ikke systemadministrasjon og er ikke brukt til å omgå dette.
  Studio PR116 dokumenterer testkobling fra 02.11; dette er ikke ferskt driftsbevis
  for denne leveransen og gir ikke tilgang til produksjons-WordPress.

## Implementasjon

Pluginversjon i kode og produksjon: **0.10.9**. Innsamlingsprotokoll: **1.0.0**.

- Eksisterende plugin får «Spilleroppfølging – kildeinnsamling» under Fotballrobot.
  Registrert adminadresse, bekreftet gjennom installert REST-rute:
  `https://www.radiorubben.no/wp-admin/admin.php?page=rrfr-source-collection`.
  Siden er installert og faktisk PHP-rendering på produksjonsserveren er kontrollert.
  Den viser Utilgjengelig fordi VPS-tilkobling ikke er konfigurert. Innlogget
  nettleserkontroll ble ikke fullført; nettleseren viste innlogging.
- Siden henter faktisk VPS-status med tre sekunders tidsgrense. Den skiller
  utilgjengelig, pauset og kjørende, bevarer siste kontakt og viser sist start,
  siste vellykkede runde, norsk neste kjøretid, profiler/klubber, tellinger/logg,
  kandidatinnboks og eksisterende artikkelgodkjenning. Klubbdata merkes med faktisk
  observasjonstid; et klubbnotat er ikke ny overgangsdokumentasjon.
- Start/pause/gjenoppta bruker eksisterende adminrolle og WordPress-nonce.
  Start gjør bare en autentisert, idempotent innlegging i SQLite-kø på VPS.
  Ingen innsamling i WordPress-sideforespørselen. Pause berører bare innsamleren.
  Pågående eksterne lesekall får avsluttes; neste leveringsgrense sjekker pause.
- Minsterettighet `rrfr_collect_sources` gir bare monitor/profil-/historikklesing
  og eksisterende kildemottak/kandidatforslag. Ingen bruker/rolle opprettes automatisk.
  Kandidatavgjørelser, spillerendringer og publisering beholder administratorgrensen.
- Python-komponenten er en klient til samme plugin, i samme repository. Den henter
  WordPress-kontrakten først og på nytt før levering, bruker eksakte ID-er, lagrede
  kilder, relevante innlegg i alle statuser og kandidatstatus. Ingen wp/v2/posts-skriving,
  database-/metadataskriving, rr_poll, alternativ sportsmotor eller AI-retry.
- SQLite gir varig jobbkø, sjekkpunkter, leveringsjournal, sperrede kildeverter,
  begrensede evidensutdrag/kildehash-er og logg. Kernel-flock hindrer parallelle runder.
  Usikker POST leses tilbake. Hvis GET ikke bekrefter lagring, gjentas POST aldri
  blindt – den må avklares. Eksisterende fakta og menneskelige valg overskrives ikke.
- Søkeleverandør: OpenAI Responses web_search, deretter separate direkte lesinger
  av konkrete offentlige sider. Søkesnutter attesteres aldri som lest innhold.
  Modellen velges eksplisitt i privat konfigurasjon; ingen modell-/nøkkelbruk er
  bekreftet i denne økten. To separate modelltrinn trekker ut og kontrollerer fakta;
  lokale kontroller binder belegg til faktisk lest tekst og numerisk NFF-profil.
  Modellkontroll er ikke bevis for redaksjonell sannhet; manuell sluttgodkjenning består.
- Historisk rotasjon følger nøyaktig UTC-døgn modulo 5 og femårsbolk modulo 4.
  coverage=partial. Alle tre kandidatbeviskategorier og identitetsanker kreves.
- Feature/videoformat videreføres. Denne tekstklienten aksepterer bare kontrollerbart
  tilgjengelig transkript, aldri avspiller/tittel. Manuell videokontroll er fortsatt
  mulig gjennom eksisterende kildekontrakt. Nora-reelen sperres særskilt som behandlet.
- Kildesvikt oppdaterer ikke vellykket sjekkpunkt. Tomt, fullført søk er en egen
  logghendelse. Testmodus oppdaterer ikke leveringsvannmerker.

## Driftsgrenser og kjent gjenstående arbeid

Ingen kjørende generell VPS-jobbmotor er bekreftet. Den medfølgende systemd-timeren
er et **installasjonsforslag som må avstemmes mot faktisk VPS-inventar** før bruk.
Den kjører 00/06/12/18 UTC (01/07/13/19 norsk vintertid), hver sjette time også
gjennom sommertidsskiftet. Tjenesten heter `rrfr-source-collection.service`, med
`rrfr-source-collection.timer` og en egen enqueue-oneshot.

Rammer: én runde, 45 minutters totalgrense, 15 sekunder per kildelesing, 90 sekunder
per modellkall, maks 100 modellkall og 100 kildelesinger per runde, maks 16 lesinger
per vert og tre forslag per spiller/søkeøkt. Ingen automatisk modell-retry. Kun
forbigående transport/502/503/504 får avgrenset retry. 401/402/403/451, robotsperre
og identifisert betalingsmur lagres som sperret; nye forsøk krever operatøravklaring.
Kun serverens faktiske omdirigering til identisk normalisert URL (for eksempel NFFs avsluttende skråstrek) følges, med ny DNS-/robotskontroll. Andre omdirigeringer avvises. Dette kan gi lavere dekning som vises
som delvis/feil, aldri falsk fullføring. Per-runde sidecache reduserer belastning.

Tjenesten binder kun 127.0.0.1. Nginx-eksemplet må settes inn i bekreftet eksisterende
TLS-vert; ingen ny offentlig port. HMAC med tidsstempel/engangsnonce beskytter kontroll.
Kildeutgang må beholde vertens beskyttelse mot private nett/IP-adresser; klienten
avviser private DNS-svar, men DNS-validering alene er ikke en nettverksbrannmur.
Ingen endring av Studio/PHP-isolasjon eller test-WordPress er gjort.

Før produksjonsbruk gjenstår:

1. Ferskt VPS-inventar: tjenester/timere, fil-/brukerrettigheter, ledig lokal port,
   TLS-vert og tillatt utgang til WordPress, OpenAI og offentlige kilder. Bekreft at
   annen spillerinnsamling ikke allerede kjører. Gjenbruk egnet eksisterende scheduler.
2. Konfigurer en autentisert innsamlingsidentitet med `read` og
   `rrfr_collect_sources`, uten manage_options/edit_posts/publish_posts. Bruk eksisterende
   godkjente serviceidentitet dersom egnet og WordPress Application Password over HTTPS.
   WP-konstanter: `RRFR_COLLECTION_URL` (bekreftet VPS-TLS-URL med stien
   `/rrfr-source-collection/v1`) og `RRFR_COLLECTION_CONTROL_SECRET` (minst 32 tegn).
   Verdiene må legges inn privat på serveren; ikke i Git, nettleser eller logger.
3. Installer VPS-filene i valgt, dokumentert driftsmappe, egen systembruker med kun
   tilhørende state-katalog, og private miljøverdier etter `config.env.example`.
   Modell og API-tilgang/budsjett må kontrolleres. Start pauset og i dry-run.
4. Prøv ekte innlogget WP-side, scoped API, HMAC/nonce, utilgjengelig-status,
   start/pause/gjenoppta og en VPS-runde uten innlegging. Kontroller faktisk dekning,
   datotolkning, kandidater og faktakontroll med ekte, tilgjengelige kilder.
5. Sett avgrenset leveringsmodus etter bestått prøve. Verifiser første reelle runde,
   WordPress-kvitteringer, UI og at manuell tekstgodkjenning fortsatt kreves.
   Først da deaktiveres kun ChatGPT-oppgave `6aac6cc9f4e88191a79aa38a966df0c2`.

## Tester

Kjørt lokalt på Python 3 og native PHP 8.3.6, isolert fra produksjonsdata:

```sh
python3 -m unittest discover -s operations/player-source-collection -p 'test_*.py' -v
python3 scripts/test-fotballrobot.py
```

30 Python-tester består, inkludert faktisk lokal HTTP-kølegging under to sekunder,
HMAC/replay, pauset profil før levering, semantisk duplikatavvisning, timeout etter POST,
uavklart levering uten dobbel POST, sjekkpunktbevaring, kandidatbevis og 20 rotasjonsbøtter.
71 PHP-filer består syntakskontroll; alle 31 PHP-suiter består. Eksisterende
kandidat-/kildetester prøver faktisk native validering med innsamlingsrettigheten og
avviser kandidatgodkjenning. GitHub CI har i tillegg bestått 17 native REST-/admin-
integrasjonskontroller i en separat WordPress 7.1.3 med syntetiske data. Installasjonstester
kontrollerer plan uten writes, baselineavvik, privat backup/readback og tilbakeføring ved
feilet WordPress-kontroll. Produksjonens nye REST-rute og player-monitor er i tillegg
lest tilbake via autentisert WPVibe etter installasjon. Ingen ekte modell-/kildekall,
innlogget visuell adminprøve, dedikert serviceidentitet eller reell VPS-kjøring er testet.

## Faktisk WordPress-installasjon

- Installert kildecommit: `bd19349985cccc78cbf6f480204c40822cc6f1ed`.
- Selektiv deploy: [GitHub Actions 41682603229](https://github.com/toystad461/radiorubben-web/actions/runs/41682603229),
  fullført 03.11.2026 kl. 17:16:43 UTC. Deployworkflow på egen operasjonsgren,
  commit `0711696da67b59cd11aff8151eec9314cf7a5370`, peker på fast kildecommit.
- Bare fem pluginfiler er installert. Eksakt baselinekontroll, lås mot annen deploy,
  PHP-kontroll, atomisk filbytte og etterhash er gjennomført. Backup ligger privat
  i `~/.radiorubben-deploy/backups/player-source-collection-bd19349985cccc78`.
- Native WordPress-kontroll bekreftet aktiv plugin 0.10.9, protokoll 1.0.0,
  REST-rute, registrert adminmeny, faktisk adminrendering og 12 profiler.
  Tilstand var `unavailable`, `reachable=false`; ingen VPS-status ble oppdiktet.
- Etterfølgende autentisert HTTP-lesing bekreftet ny REST-rute og fortsatt
  `automatic_proposals=true`, `publication=manual_approval_required`, nødvendige
  features og planlagt `next_check`. API-versjonen for player-monitor er fortsatt 0.10.4.
- Ingen innlegg, kilder, kandidater, brukere, roller, nøkler, WordPress-innstillinger
  eller cron-planer ble endret av installasjonen. Den gamle ChatGPT-innsamlingen er aktiv.
- Maskinlesbar kvittering: [wordpress-install-receipt.json](wordpress-install-receipt.json).

## Tilbakeføring

Pause og stopp kun rrfr-source-collection-tjenesten/timeren. Behold SQLite, kø,
leveringsjournal, kildeinnboks og historikk. Tilbakefør bare de fem WordPress-filene
fra privat backup etter kontroll av at de fortsatt har denne leveransens bytes.
Ingen gjenoppretting av database, ingen sletting av ny innholdshistorikk, ingen
automatisk gjenoppliving av gammel ChatGPT-oppgave uten avstemming.

## Primærreferanser brukt ved implementering

- https://developers.openai.com/api/docs/guides/tools-web-search
- https://developers.openai.com/api/docs/guides/structured-outputs
- Eksisterende PlayerMonitor, PlayerCandidates, MediaSources, Players og PublicationGate.
- https://github.com/toystad461/radiorubben-web/pull/108
- https://github.com/toystad461/radiorubben-web/pull/106 (separat, ingen kode tatt inn)
- https://github.com/toystad461/radiorubben-studio/pull/116 (historisk testdrift)
