# Fotballrobot 0.10.8 — full testpakke, 18. oktober 2026

## Resultat og avgrensning

Pakken inneholder **alle 38 kjørefilene**, byteidentiske med `after` i
`scripts/details-release.json` fra PR #92, commit
`1a162231716559e5f7d37ba26647c524ed367744`. Den historiske pakken inneholdt bare
sju endrede filer og kunne ikke brukes alene på en tom installasjon.
Ingen av de 38 kjørefilene er reparert eller skrevet om i denne leveransen.
Testverktøy, kontrollsummer og et separat MU-tillegg er nye.

**Ikke installert på VPS.** SSH til 5.249.252.78 svarte `Network is unreachable`.
Nettleseren hadde ingen eksisterende innlogget økt. Vi har derfor ikke kunnet
gjenta verts-, database-, nettverks- eller innloggingskontrollene på serveren.
Produksjon, main, eksisterende PR-er, jobboppsett og artikler er ikke endret.

Dette gjenoppretter dokumentert 0.10.8-kode, ikke en fersk måling av hele
produksjonsserveren. PR #92 dokumenterer installasjonen 14. oktober og kontroll
av alle 38 baner. CI-kjøring 38566150342 er verifisert som vellykket, men er et
testbevis, ikke selvstendig bevis for produksjonsinstallasjon. GitHub hadde ingen
publiserte release-objekter eller tagger ved kontrollen.

## Kildeavstemming

| Grunnlag | Kontroll |
| --- | --- |
| Main `c349fc24` | Mangler Fotballrobotens hovedfil. Brukes ikke som robotpakke. |
| PR #63 / `75326d1` | 32 kjørefiler, kontrollert mot fastlåst 0.10.4-runtime `29e49e75`. |
| 0.10.5 / PR30-manifest | 35 manifestbaner, åtte endringer. Før-hasher kontrollert mot angitt base. |
| 0.10.6 / PR86-manifest | 37 manifestbaner, ti endringer. Før-hasher kontrollert mot angitt base. |
| 0.10.7 / kickoff-manifest | 37 manifestbaner, to endringer. Før-hasher kontrollert mot angitt base. |
| 0.10.8 / PR92-manifest | 38 filer, sju endringer fra 0.10.7. Alle etter-hasher stemmer med pakken. |

Fra 0.10.4 til 0.10.8 er åtte eksisterende filer endret og seks nye kommet til.
`provenance.json` lister hver fil, kildecommit og kontroll. Historiske belegg
ligger uendret under `evidence/`; gamle manifest omtales ikke som dagens runtime.
Kilde: [PR63](https://github.com/toystad461/radiorubben-web/pull/63),
[PR92](https://github.com/toystad461/radiorubben-web/pull/92),
[Studio PR96](https://github.com/toystad461/radiorubben-studio/pull/96) og
[Studio PR97](https://github.com/toystad461/radiorubben-studio/pull/97).

## Reproduksjon og verifisering

Krever Python 3.10+ og PHP 8.3+ med DOM, mbstring og øvrige vanlige CLI-utvidelser.
Ingen installasjon av avhengigheter, nedlasting eller eksterne oppslag utføres
av testkommandoene. Testpakken skal pakkes ut utenfor offentlig webrot.

```sh
python3 verify.py
sha256sum -c runtime.sha256
python3 run-tests.py --php php
python3 rebuild-zip.py . ../RadioRubben-Fotballrobot-0.10.8-Testpakke.zip
```

ZIP bruker faste tidsstempler, sortert filrekkefølge og ukomprimert lagring.
Samme filsett gir identiske ZIP-bytes. Ikke legg testlogger eller egne filer
inn i pakken: verifiseringen avviser både manglende og ekstra filer. Lagre
logger utenfor pakken. `FILES.json` dekker alle pakkefiler; `runtime.sha256`
dekker kjørefilene. Verifiseringen binder i tillegg produksjonsmanifestet til
dets fastlåste Git-blob-ID. En kontrollsum er ikke en digital signatur.

For å bygge på nytt fra GitHub: hent den separate recovery-grenen med full
Git-historikk og kjør `python3 recovery-0.10.8/build.py /ny/tom/utdatamappe`.
Byggeren leser runtime og historikk direkte fra fastlåste Git-objekter, ikke
fra eventuelle redigerte arbeidsfiler. `source-builder.py` i ZIP-en er en
referansekopi av denne byggeren; den kjøres fra recovery-mappen i repoet.

## Kontrollerte testgrenser

| Del | Faktisk kontroll | Gjenstående |
| --- | --- | --- |
| Kampregistrering | Uendret Site Functions-handler registrerer oppdiktet kamp fra et lokalt Fotballdata-svar. | HTTP/innlogging på VPS. |
| Lagoppstillinger | 11 startere + 2 reserver på begge lag; robot og avstemningsparser er enige; mangler og kildekonflikter avvises. | Visning og lagring i ekte test-WordPress. |
| Dagens Bremnesing | Reell start-/stemmehandler med minnelagring; start, tillatelser, nonce, manglende tropp, stengt kamp, stemme og dobbeltstemme. Byttet spiller beholdes som kandidat. | Faktisk nettleserflyt og autentisering. |
| Referat | Eksisterende writer-flow, match-followup og kvalitetskontroller med lokale svar; utkast, idempotens, kildeendring og manuelle tekster. | Ingen vurdering av ekte AI-generert prosa; ingen API-kall. |
| Redaksjonell godkjenning | Eksisterende ReviewDesk/Newsroom/PublicationGate-tester for rolle, revisjon, innhold/fakta og menneskelig beslutning. | Studio–WordPress-transport og samlet grensesnitt. |
| Installasjon | Frisk installasjon, filhash, feil pakke, eksisterende filer, lenker, endringer etter installasjon og gjentatt tilbakeføring i midlertidige mapper. | Faktisk vertskontroll på VPS. |

De 26 opprinnelige PHP-testseriene bruker lokale mocks og historiske fixtures.
Ti nye scenarier bruker bare oppdiktede navn, lagopplysninger og stemmer.
Testkamp 900000001/900000002 er syntetisk. ID 30365/klubb 827 brukes kun som
eksisterende tillatt lagvelger; dette er ikke en påstand om en faktisk kamp.
Fake API-verdier i testfilen sendes bare til en lokal funksjonsstub.
Ingen test leser wp-config.php, en live database eller produksjonsnøkler.

## Funn som fortsatt begrenser hele kampreisen

1. Dagens Bremnesing tilhører Site Functions, ikke Fotballrobotens 38 filer.
   Sju filer fra main `c349fc24` følger som **teststøtte**, og installeres aldri
   av robotinstallatøren. Nåværende installerte Site Functions må avstemmes
   separat før nettlesertesten.
2. Denne Site Functions-koden stenger avstemningen ved **85 minutter**, og
   importen krever hjemmekamp for Menn A eller Kvinner A. Testene bekrefter
   begrensningene; de er ikke rettet eller presentert som ønsket sluttfunksjon.
3. Main bruker Fotballdata for kampregistrering. I isolert test er transport
   og API-nøkler sperret. Den nye transportstubben finnes bare i CLI-testene;
   den åpner ingen testserverrute og gjør ingen unntak fra nettverkssperren.
4. Studio PR #97 er fortsatt ikke installert og er bare lesende. Dette er
   derfor ikke en ferdig ende-til-ende Studio–WordPress-test.
5. API-ens gamle versjonsfelt i enkelte robotruter er ikke et sikkert bevis
   på pluginversjon. Kontroller hovedfil og alle manifesthasher.

## Installasjon på test-VPS når tilgang er tilgjengelig

Installatøren kan bare legge inn **inaktiv** robotkode på `vps-5363103`,
i `/srv/radiorubben-test/wordpress`. Den godtar ingen alternativ vert,
produksjonssti, sikkerhetsunntak eller overskriving av eksisterende robot.
Den starter ingen tjenester, endrer ingen innstillinger og aktiverer ingenting.

Krav før installasjon: verifisert SSH-vertsidentitet eller innlogget
Uniweb-konsoll, korrekt VPS, innloggingsbeskyttet HTTPS, separate testdata,
ingen produksjonsnøkler i filer/prosessmiljø/database, ingen eksterne robotjobber
og gjennomgått aktiv tjeneste-/timeroversikt. Kopier pakken til privat mappe
utenfor webroten. Kjør som root på riktig testvert:

```sh
python3 install-test.py check
python3 install-test.py install
python3 install-test.py after
```

Skriptet kontrollerer kjente konfigurasjons- og isolasjonsfiler byte for byte
mot PR96, `PrivateNetwork=yes`, tjenestestatus, eksisterende vertssperret
`rr-test-verify`, database `rr_test_wp` med lokal socket-bruker `rr-wordpress`,
inaktiv robot, kjente integrasjonsopsjoner og kjente testjobber. Ukjent avvik
stopper; sikkerhetskontrollen skal ikke omskrives for å få grønt lys.
Disse automatiske kontrollene erstatter ikke fersk kontroll av øvrige
miljøvariabler, PHP-pooler eller ukjente serverjobber.

38 kjørefiler legges inn som én katalog. MU-tillegget `guard.php` legges inn
før robotkatalogen, separat fra original runtime. Det krever riktig
vert/sti/database/URL/staging, sperrer all WordPress-HTTP, e-post, cron og
planlagt eller direkte artikkelpublisering. Utkast er tillatt. Ingen
produksjonsdatabase, WordPress-config, API-nøkkel eller Studio-fil følger med.
Infrastrukturfilene i `evidence/` er kontrollgrunnlag og installeres ikke.

**Ikke aktiver roboten eller så ekte testdata som del av denne pakken.**
Kjør først de medfølgende offline-prøvene på VPS-ens PHP. En senere aktiv
WordPress-test trenger egen testdatabasetilbakeføring og en gjennomgått,
lokal fixtureadapter uten nettverksunntak. Ikke legg inn ekte API-nøkler for
å få testknappene til å fungere. Publiseringsknappen skal fortsatt avvises.

## Tilbakeføring

```sh
python3 install-test.py rollback
```

Forutsetter at roboten fortsatt er inaktiv. Kvitteringen ligger privat i
`/srv/radiorubben-test/recovery/football-0.10.8/receipt.json` og dokumenterer
at den tidligere robotkatalogen og testsperren var fraværende.
Alle installerte filer og sperren må fortsatt ha riktige hasher.
De flyttes til privat karantene; ingen permanent sletting utføres.
Tilbakeføring kan gjentas og videreføres etter et avbrutt flyttetrinn.
Senere manuelle endringer, ekstra filer eller lenker stanser tilbakeføring.

Ingen databaseinnstillinger eller artikler endres av installatøren, så den
har ingen databasebackup å gjenopprette. Hvis noen aktiverer pluginen eller
oppretter testdata etterpå, gjelder ikke denne filtilbakeføringen som full
gjenoppretting av miljøet. Bruk da separat, kontrollert testdatabasebackup.

## Oppbevaring og videre arbeid

GitHub er original for kildekode og testverktøy. En ferdig ZIP kan oppbevares
i den eksisterende OneDrive-mappen `Radio Rubben/07 Plattform og driftsdokumentasjon`
som driftskopi, med denne rapporten og kontrollsummene inne i pakken.
Neste steg er autentisert tilgang til test-VPS og fersk isolasjonskontroll;
deretter inaktiv installasjon og lokal kontroll før aktiv kampreise vurderes.
