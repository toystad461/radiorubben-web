# Bremnes fra G13/J13 og ukesartikkel

Status: implementert og testet i utviklingsgren. Ikke installert eller aktivert på produksjon.
Bygger på Fotballdata-adapteren og artikkelskriveren i PR #29.

## Omfang

- Henter ferskt lagregister og kampoversikt fra Fotballdata for Bremnes, klubb 827.
- Registerets G/J-klasser fra 13 år og oppover, samt seniorlag, er med i ukesoversikten.
  Lag-ID er identiteten; to lag med samme navn forblir forskjellige lag.
- Nye automatiske kampoppsummeringer gjelder ungdomslag fra G13/J13. De eksisterende
  seniorreferatene har fortsatt sin eksisterende jobb, slik at to motorer ikke skriver samme kamp.
- Manuell kampgrunnlagsflyt støtter nå også de kvalifiserte ungdomslagene.
- Hjemme- og bortekamper inkluderes. Interne oppgjør dedupliseres på kamp-ID.
- Dette er lagets registrerte klasse, ikke dokumentasjon på enkeltspilleres alder.

## Kampoppsummeringer

Kontroll hver halvtime. Krever eksplisitt godkjenning av sluttresultatet fra dommer eller krets,
ikke bare passert kampstart eller et foreløpig 0–0. Avlyst/utsatt/avbrutt/walkover avvises.
Kampen må starte etter aktivering; ingen masseproduksjon av historiske saker.
Et uendret bekreftet resultat må ha vært observert i minst 30 minutter før egen skrivejobb
settes i kø. Kilden hentes på nytt før skriving. En rettelse starter ventetiden på nytt.

AI bruker den eksisterende referatstilen og separat faktakontroll. Den nye automatiske
kampfeed-adapteren inneholder resultat, lag, turnering, bane og avspark, men ikke målscorere,
bytter eller hendelsesforløp. Den lager derfor korte oppsummeringer når datagrunnlaget er tynt.
Den manuelle eksisterende kampflyten har fortsatt sin detaljerte kildeadapter.

## Søndag kl. 18.00

En enkelt WordPress Cron-hendelse planlegges etter Europe/Oslo. Neste tidspunkt beregnes
på nytt hver uke, slik at sommer-/vintertid blir korrekt. Første kommende tidspunkt ved
aktivering før 4. oktober 2026 kl. 18.00 er søndag 4. oktober.

Artikkelen dekker mandag 00.00 til neste mandag 00.00 (slutt eksklusiv). Første uke er dermed
5.–11. oktober. Den inneholder alle kvalifiserte kamper, i tidsrekkefølge, med lagets registrerte
navn/klasse, motstander, hjemme/borte, dato, tid, bane, turnering og lenke til kampregistreringen.
Deterministisk tekst bevarer kampfakta nøyaktig; ingen AI-kostnad for ukeoversikten.

Ved kildefeil opprettes ingen tom oversikt. Nytt forsøk etter 30 minutter beholder opprinnelig
måluke, også ved forsinket kjøring mandag. Etter måluken stoppes innhenting av den utløpte
uken med synlig driftsfeil. En ekte tom uke gir et utkast som sier at ingen kamper er registrert.

## Tilgang og godkjenning

`Fotballrobot → Bremnes fra 13 år` viser status, feil og utkast, og har aktivere/stans-knapp.
Endringer krever administrator, edit_posts og gyldig nonce. Automatikk er av ved installasjon.
Privat `RRFR_FOTBALLDATA_CID` og `RRFR_FOTBALLDATA_CWD` må finnes som PHP-konstanter eller
miljøvariabler. Aktivering kontrollerer API-data og eksisterende AI-oppsett før planlegging.
Den separate eldre `RRFR_FOTBALLDATA_ENABLED`-bryteren endres ikke.

Artikler kategoriseres som Sport → Fotball → Bremnes IL når kategoriene finnes.
Artikler lagres som draft til redaksjonell gjennomlesning i WordPress. Ingen auto-publisering,
ingen e-post. Denne modulen bruker ikke spillerforslagenes e-postgodkjenning. Menneskelige
redigeringer, publiserte artikler og utkast i papirkurv overskrives aldri. Én nøkkel per kamp/uke
og en utkastreservasjon før AI-kall hindrer duplikater og automatisk gjentatte betalte kall.
En avbrutt eller mislykket skrivejobb må vurderes manuelt; status/feil ligger på utkastet.
Ikke fjern låser eller utkastreservasjoner før pågående jobber er kontrollert.

Kun tillatte sportsfelter lagres/sendes til skriveren. Kontaktdata fra API-et, nøkler og
credential-URL-er utelates. API-transportfeil maskeres. Ingen nye persondata hentes.

## Drift før aktivering

1. Installer testet plugin med avhengighetene fra PR #29 og privat API-konfigurasjon.
2. Kontroller eksisterende seniorjobb og at den fortsatt er eneste automatiske eier av seniorreferater.
3. Sett serveren til å utløse WordPress Cron hvert minutt. Trafikkstyrt WP-Cron alene gir ikke
   garanti for kjøring kl. 18.00. Kontroller at lange AI-jobber får tilstrekkelig kjøretid.
4. Åpne administrasjonssiden, kontroller tilgang og aktiver. Kontroller kø, testutkast og
   faktisk lokal klokkeslettvisning på serveren. Gjør først dette i staging.
5. Ved stopp fjernes planlagte jobber. Lagrede artikler og historikk beholdes.

## Verifisert 29. september 2026

- Ferske lesekall: 48 lag totalt, 18 innenfor omfanget; 459 klubbkamper totalt, 295 innenfor
  lagutvalget. Rensede sportsdata passerte den faktiske PHP-normaliseringen.
- Kommende ukesvindu 5.–11. oktober hadde 3 kamper i dette øyeblikksbildet; søndagsjobben
  henter data på nytt, den gjenbruker ikke denne tellingen.
- `tests/club-coverage.php`: 59 isolerte kontroller, inkludert aldersgrense, hjemme/borte,
  doble navn/ID, kildekonflikt, status, persondatafiltrering, tidsgrenser, DST, duplikater,
  menneskelige redigeringer, reell jobbflyt med mock API, feilhåndtering og ingen publisering.
- Eksisterende parser-, skriver-, spiller-, godkjennings-, lærings-, rapport- og bootstrap-tester
  er kjørt lokalt med PHP 8.2 WASM. Ingen betalte AI-kall, e-post eller produksjonsendringer.
- Full WordPress/stagingtest, server-cron og faktiske AI-oppsummeringer gjenstår.
