# Automatisk kampoppfølging – 0.10.6-kandidat

## Konkret årsak og produksjonskontroll

Riktig repository er `toystad461/radiorubben-web`, ikke det separate
TypeScript-repositoryet `RadioRubben-robot`. Arbeidet er stablet på PR30,
`feature/bremnes-youth-weekly`, head `661228524d6ff6e05801027951cc0c82738795ef`.
Denne grenen bevarer rekonstruert produksjon, 0.10.5, klubbdekningen og nyere
AI-policy. Main har eldre fotballkode og brukes ikke som runtime-grunnlag.
PR83s dashboard/API-arbeid og de andre nettsidegrenene endres ikke.

Autentiserte, utelukkende lesende WPVibe-kontroller 10. oktober bekreftet:

| Steg | Dokumentert funn |
| --- | --- |
| Kildeinnhenting | Arkivets NFF-snapshot har 7–1, sju registrerte Bremnes-mål og ett motstandermål. `nff.fetched=1791573176`. Resultat/hendelser er ikke alene bevis for kampslutt. |
| Speaker/dashboard | Arkivert `state.finished=true`. Arkivet er laget kl. 08.38.08, ikke kampkvelden. |
| Arkivering | Aktiv `rr-site-functions/inc/bremnes-poll-rules.php:39–47` krever `state.finished` og skriver første snapshot med `saved_at=time()`. Eksisterende arkiv erstattes ikke. |
| Kø | Aktiv `includes/match-jobs.php` reagerer på `added_option` / `updated_option` for arkivet. Kamp 8985501: `queued`, `prepare`, `confirmed_at=created_at=1791614288`, `due_at=1791617888`. Det er 08.38.08 → 09.38.08 norsk tid. |
| Alternativ observasjon | `MatchJobs::observe()` kalles av POST `/matches/{id}/queue`. Repositoryets søk viser ingen cron-, dashboard- eller bakgrunnskaller til denne metoden. Kalleren må allerede ha bekreftet sluttstatus; metoden gjør ikke egen automatisk oppdagelse. |
| Klubbautomatikk | Aktiv 0.10.5 henter klubblisten, men `runTick()` hopper over seniorreferater for å la seniorjobben eie dem. Den fyller dermed ikke oppdagelseshullet for denne kampen. |
| Cron | `rrfr_match_job` var planlagt 10. oktober 07.38.08 UTC. Klubbkontrollen og øvrige tilbakevendende jobber var også registrert. `DISABLE_WP_CRON` er ikke definert. Dette dokumenterer en planlagt WordPress-jobb, ikke en trafikkuavhengig hostscheduler. |
| Skriving/kontroll | Seniorjobben har separate prepare/write/review-steg. Writer har egen lås, faktakontroll, språkvask og eventuell ny faktakontroll. Jobben lager bare draft. |
| Godkjenning | Eksisterende ReviewDesk / Studio-bro og PublicationGate beholdes. Teknisk kvalitetskontroll er ikke sluttgodkjenning. |
| Artikkelsøk | Søk på `_rrfr_ai_match`, `rubben-kamp-8985501` og kampnummer i slug fant ingen `post`. Et bredt søk fant ID847, men kontroll viste `post_type=rr_match`: kampkort, ikke referat. |

De ferske kontroller før kl. 09.38 viste fortsatt uendret kø. Det er derfor
**ikke dokumentert cron- eller skrivefeil for denne jobben** ved disse kontrollene:
den var ennå ikke forfalt. Den konkrete oppstartsfeilen er at seniorflyten ikke
observerer kildens sluttstatus selv. Uten dashboardarkiv eller eksplisitt API-kall
finnes ingen automatisk vei inn i køen. Manglende manuelt «Slutt» kan dermed hindre
oppstart. Arkivets og køens identiske tidspunkt dokumenterer utløsningen kl. 08.38;
det finnes ikke en handlingslogg som beviser nøyaktig hvilken dashboardhandling
som opprettet arkivet. Det store speakerklokketallet er heller ikke en sluttid.

Det er ikke kontrollert hva Fotballdata faktisk bekreftet fredag kveld, eller
når leverandørens godkjenningsflagg først ble satt. Vi kan derfor ikke love at
ny kode ville ha skrevet akkurat kl. 22.00 fredag. Den vil kontrollere uten
dashboardbesøk og vise venting når leverandøren ennå ikke bekrefter resultatet.

## Avstemming mot aktiv kode

Aktiv pluginheader var 0.10.5. Lesbare runtime-filer for pluginheader,
club-automation, club-coverage, fotballdata, report og publication-gate samsvarte
med valgt gren etter normalisering av linjeslutt og sluttblanklinjer. Aktive
match-jobs og robot ble lest og kontrollert mot samme kilde. Forskjellene i
writer, review-desk og newsroom var WPVibes maskering av token-/Authorization-
uttrykk. Maskert kilde er **ikke bytebevis**; ingen maskert fil er kopiert tilbake
som kode. Dashboardets store bremnes-poll-test.php overskrider WPVibes 64 KiB-grense
og kunne ikke leses derfra. «Slutt»-handlingens detaljforklaring bygger derfor på
den eksisterende Site Functions-kopien, mens arkivfunksjonen er kontrollert i
aktiv produksjon. Ferske SSH-hasher av hele det avgrensede filsettet kreves før
eventuell installasjon. Historiske manifester og releasekvitteringer er uendret.

## Implementert

- `MatchFollowup` registrerer en femminutterskontroll. Den henter ferske,
  validerte klubb-/kampdata med eksisterende private Fotballdata-innstillinger.
  Ny senioroppdagelse dekker hjemme og borte, kampstart etter aktivering og de
  siste sju døgnene. Utsatt, avbrutt, avlyst og walkover behandles som særskilt
  status og gir ikke et oppdiktet sluttresultat.
- Bare uttrykkelige `FinalResultApprovedByDistrict` / `FinalResultApprovedByReferee`
  med gyldig resultat og identitet bekrefter ferdig kamp. 0–0 er et gyldig resultat
  når disse kravene er oppfylt. En planlagt kamps standardresultat er ikke nok.
  Kilden hentes videre etter avspark; avspark brukes bare til å avgrense kontrollen,
  aldri til å beregne sluttid. Ingen ny speaker-/avstemningsstatus skrives.
- Den dokumenterte adapteren leverer ingen verifisert sluttfløyte-tid. Timen
  regnes derfor fra første kildebekreftelse. Gjentatte kontroller forskyver ikke
  timen. Endret resultat før skriving starter en ny bekreftelse og ventetid.
  Eksisterende kø, inkludert 8985501, beholder allerede lagret frist.
- Seniorjobben kontrollerer kilden på nytt før hvert steg. HTML-kampkortets lag,
  kampnummer, turnering, avspark og resultat må stemme med Fotballdata før
  faktagrunnlag lagres. Manglende og motstridende data gir venting/feil og nye
  kontroller. Speakerarkivet er ingen erstatning for den nye kildesjekken.
- En paginert kontroll av opptil 100 lagrede jobber per kjøring reparerer tapte
  enkelthendelser, også for allerede registrerte jobber eldre enn sju døgn.
  Ikke-betalte steg har opptil seks forsøk med økende ventetid. Globale kildefeil
  prøves i avgrensede serier med ventetid opptil én time og synlig varsling.
  En eksplisitt, tilgangs-/noncebeskyttet gjenopptakelse finnes på robotsiden.
- `MatchWork` lagrer AI-tekst og hvert ferdig kontrollsteg varig, uavhengig av
  transientens levetid. En omstart fortsetter kontrollen uten ny generering.
  Et AI-kall reserveres før utsending; ukjent utfall blir blokkert, ikke automatisk
  betalt om igjen. Et ferdig kontrollsteg kan lagre utkast uten nytt modellkall.
  Eldre avbrutte betalte steg har ikke slike kontrollpunkter og blir stoppet
  for vurdering. Uavklarte betalte låser fjernes ikke automatisk.
- Atomiske jobblåser serialiserer oppdagelse og kjøring. Stale, ikke-betalte låser
  kan bare fjernes ved sammenligning med den gamle eierens tidsstempel, etter
  30 minutter. Writer kontrollerer eksisterende artikkel igjen etter låsing.
  Metadata, ordinær slug, publiserte innlegg og papirkurv stopper duplikater.
- Automatisk oppfølging omskriver aldri et eksisterende utkast eller publisert
  referat. Nye motstridende kildedata/tilbaketrukket sluttstatus ugyldiggjør
  kvalitetskontrollen for videre godkjenning, men endrer ikke redaktørens tekst.
  Cron/CLI kan ikke bruke skriverens brukeridentitet som manuell publiseringsfullmakt.
- Dagens Bremnesing kommer bare fra en uttrykkelig lagret hjemmepris, validert
  mot lagoppstillingen. Arkivert delt førsteplass beholdes. Kampens registrerte
  sponsor kan omtales nøkternt. Bare sportslige navn/sponsornavn sendes; aldri
  stemmegiver, premievinner eller hele dashboardbeskrivelsen. Ingen pris er et
  normalt, ikke-blokkerende tilfelle.
- Eksisterende PR30-dekning av G13/J13 og eldre ungdomslag, begge kjønn og hjemme/
  borte, med én times uendret sluttresultat og søndagsoversikt er bevart. Ungdom
  får status i samme oversikt, men beholder sin eksisterende ene skriver. Ingen
  parallell ungdomsgenerering er lagt til. Reserverte, avbrutte ungdomsutkast
  beholdes for kontroll; et ukjent modellutfall gjentas ikke automatisk.
- ReviewDesk viser kamp, kildestatus, siste kontroll, planlagt behandling,
  jobbsteg/blokkering og utkastlenke. På mobil vises kampene som lesbare kort.
  Newsroom-API-et får et tillegg `matchFollowup`; eksisterende Studio-avgjørelser
  og artikkelkort endres ikke. Studio-frontenden må ta feltet i bruk separat
  dersom samme detaljoversikt ønskes direkte der.

## Kjøring uten nettsidebesøk

`scripts/fotballrobot-cron.php` kjøres med WordPress' faktiske CLI, inklusive
plugins og brukerrettigheter. Den kontrollerer/reparerer køen og utfører bare
forfalte fotballhooks. Den har antalls-/tidsgrense og lagrer et eget host-cli-
tidspunkt som vises i godkjenningsflaten. Kilde-/konfigurasjonsfeil gir feilstatus.

`.github/workflows/fotballrobot-scheduler.yml` bruker eksisterende verifisert SSH,
hostlås og tidsavbrudd hvert femte minutt. **Den er inert i denne PR-en**: den
krever main, separat installert 0.10.6 og `RRFR_SCHEDULER_ENABLED=true`. Ingen
variabel, hemmelighet, cron-konfigurasjon eller produksjonsfil er endret.
GitHub kan forsinke planlagte kjøringer; fem minutter er kontrollintervallet,
ikke en leveringsgaranti. Et lokalt hostscheduler-oppsett kan kjøre samme script
ved behov. Kildedata og utkast sendes ikke til workflowloggen.

Før aktivering må det avgrensede filsettet installeres mot ferske serverhasher,
og `flock`, `timeout`, WP-CLI og eksisterende SSH-tilgang verifiseres. Ikke bruk
PR30s gamle fullpakke eller gammel main som installasjon. Første faktisk eksterne
kjøring må vise et ferskt `rrfr_match_followup_runner`, også uten HTTP-trafikk,
og etterfølges av kontroll av kø/utkast og manuell godkjenning. Dette er en
utrullings-/driftskontroll som gjenstår, ikke noe lokale tester beviser.

## Verifisert lokalt

- 59 PHP-filer syntakskontrollert og alle 24 isolerte robotsuiter bestått med PHP
  8.2.34, DOM og mbstring. Testene bruker syntetiske data, ingen betalte AI-kall.
- Nye kilde-/jobbsuiter prøver faktisk ClubCoverage/Fotballdata, MatchJobs,
  MatchFollowup, låser og CLI-script. Kontrolltilfellet bruker ID8985501 og de
  konkrete køtidspunktene, med syntetiske kampdata og 7–1. Speaker står fortsatt
  pågående, og resultatet blir ett draft. Tapte cron-steg, 0–0, avvik, særskilte
  statuser, avbrudd, levende låser, eierkontroll og avgrensede forsøk er prøvd.
- Den faktiske Robot/HTML-parseren prøves for selvstendig kildebekreftelse, 0–0
  og motstridende identitet/resultat. Den faktiske Writer prøves for varige
  skrive-/fakta-/språksteg, forsvunne transients, ferdig kontroll uten ekstra
  modellkall, feil bruker og endret faktagrunnlag.
- PublicationGate prøves for endrede kilder, tilbaketrukket sluttstatus, manuelle
  redigeringer og publiseringssperre ved cron med skriverens brukerrettigheter.
  Eksisterende negative godkjennings-/metadata-/rolletester og gyldig manuell
  godkjenning består.
- Leseflater/godkjenning og ny kampoversikt er prøvd i skjult Edge ved 390 og
  1280 px: ingen dokumentoverflyt, godkjenning aktiv bare i riktig tilstand.
  Dette er renderte PHP-fixtures, ikke faktisk WordPress eller fysisk mobil.
- Eksisterende installasjonstest, statisk kontroll/test og bygg bestått.
  Sandkassen blokkerte først lokal nettleserstart/atomisk testfilbytte; samme
  lokale fixturetester besto utenfor sandkassen. Ingen produksjon ble berørt.

## Gjenværende usikkerhet og leveransestatus

Koden er implementert og lokalt testet. PR/CI-resultat oppgis separat i GitHub.
Ingen merge, runtime-utrulling, scheduleraktivering, kømutasjon, AI-kjøring eller
artikkelpublisering er utført i produksjon. Den virkelige kampen kan fullføre i
den eksisterende køen mens PR-en gjennomgås; kontroller derfor jobb, metadata,
slug, kvalitet og eventuell redaktørtekst på nytt før installasjon. Ingen replay
av 8985501 er nødvendig for å installere forbedringen.

Leverandørens tidspunkt for første sluttbekreftelse fredag, full byteparitet på
maskert/stor kilde, en faktisk trafikkuavhengig serverkjøring og kvaliteten på
et ekte modellsvar gjenstår å verifisere. Ungdomsjobber med et uavklart betalt
avbrudd krever fortsatt vurdering; nye automatiske, betalte gjentakelser vil
ikke bli startet for å skjule usikkerheten.
