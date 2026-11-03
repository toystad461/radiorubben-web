# Spilleroppfølging – kildeinnsamling på VPS

Arbeidsområder: System & Kvalitet, Teknisk Produksjon og Lokalt Engasjement.
Leveranse 3. november 2026. **Kode og isolerte tester; ikke installert eller verifisert i drift.**

## Avstemt eksisterende løsning

- Produksjonens `player-monitor` er lest 03.11.2026: engine
  `radio-rubben-fotballrobot`, API-versjon 0.10.4, automatic_proposals=true,
  publication=manual_approval_required, alle tre nødvendige features og planlagt
  next_check. Alle tolv profiler var enabled. Ingen uttømmende spillerliste i koden.
- Aktiv pluginhovedfil oppgir **0.10.8**. API-versjon og pluginversjon er forskjellige.
- Hovedfil, players.php, player-monitor.php, player-candidates.php og media-sources.php
  er lest gjennom WPVibe og innholdssammenlignet med
  `10e9684d6061deccc9bdd5ac39d3cf8251f4bfc4` på eksisterende
  `feature/weekly-match-stories-20261024`. Sammenligningen ignorerer avsluttende
  linjeskift fra leseverktøyet; dette er **ikke en byteeksakt serverhash-attest**.
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

Pluginversjon i kode: **0.10.9**. Innsamlingsprotokoll: **1.0.0**.

- Eksisterende plugin får «Spilleroppfølging – kildeinnsamling» under Fotballrobot.
  Måladresse etter installasjon:
  `https://www.radiorubben.no/wp-admin/admin.php?page=rrfr-source-collection`.
  **Denne siden er ikke installert og lenken er ikke verifisert som fungerende.**
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
2. Installer de fem avgrensede WordPress-filene med ekte før-/etterhasher og privat
   backup. `install_wordpress.py` nekter avvik fra baseline og støtter plan uten writes.
   Kontroller på isolert WordPress før produksjon; ingen syntetiske produksjonskilder.
3. Konfigurer en autentisert innsamlingsidentitet med `read` og
   `rrfr_collect_sources`, uten manage_options/edit_posts/publish_posts. Bruk eksisterende
   godkjente serviceidentitet dersom egnet og WordPress Application Password over HTTPS.
   WP-konstanter: `RRFR_COLLECTION_URL` (bekreftet VPS-TLS-URL med stien
   `/rrfr-source-collection/v1`) og `RRFR_COLLECTION_CONTROL_SECRET` (minst 32 tegn).
   Verdiene må legges inn privat på serveren; ikke i Git, nettleser eller logger.
4. Installer VPS-filene i valgt, dokumentert driftsmappe, egen systembruker med kun
   tilhørende state-katalog, og private miljøverdier etter `config.env.example`.
   Modell og API-tilgang/budsjett må kontrolleres. Start pauset og i dry-run.
5. Prøv ekte innlogget WP-side, scoped API, HMAC/nonce, utilgjengelig-status,
   start/pause/gjenoppta og en VPS-runde uten innlegging. Kontroller faktisk dekning,
   datotolkning, kandidater og faktakontroll med ekte, tilgjengelige kilder.
6. Sett avgrenset leveringsmodus etter bestått prøve. Verifiser første reelle runde,
   WordPress-kvitteringer, UI og at manuell tekstgodkjenning fortsatt kreves.
   Først da deaktiveres kun ChatGPT-oppgave `6aac6cc9f4e88191a79aa38a966df0c2`.

## Tester

Kjørt lokalt på Python 3 og native PHP 8.3.6, isolert fra produksjonsdata:

```sh
python3 -m unittest discover -s operations/player-source-collection -p 'test_*.py' -v
python3 scripts/test-fotballrobot.py
```

26 Python-tester består, inkludert faktisk lokal HTTP-kølegging under to sekunder,
HMAC/replay, pauset profil før levering, semantisk duplikatavvisning, timeout etter POST,
uavklart levering uten dobbel POST, sjekkpunktbevaring, kandidatbevis og 20 rotasjonsbøtter.
71 PHP-filer består syntakskontroll; alle 31 PHP-suiter består. Eksisterende
kandidat-/kildetester prøver faktisk native validering med innsamlingsrettigheten og
avviser kandidatgodkjenning. Ingen ekte modell-/kildekall, installert adminside,
WordPress HTTP-integrasjon eller reell VPS-kjøring er testet.

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
