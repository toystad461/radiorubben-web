# Fotballpilot – 5. oktober 2026

Oppfølging til designsystemet i PR #60. Piloten kjører på `http://127.0.0.1:8877/fotball-pilot/` med Next og Site Functions i den eksisterende lokale, isolerte WordPress-kopien fra 26. september. Produksjon er ikke endret. Dette er ikke en fersk kopi av produksjonsdatabasen.

## Komponenten som kan gjenbrukes

Velg sidemalen **Fotball/live** og sett inn mønsteret **RR · Fotball med kamp og avstemning** (`combined-football-live.php`). Mønsteret har to hovedseksjoner, med anker til selve avstemningen inne i kampmodulen. Det generelle fotballmønsteret fra PR #60 er beholdt.

- `[rr_match_day]` eies av Site Functions. Det viser eksisterende kamp- og stemmerenderer én gang inne i vanlig sideinnhold. Sidens header, footer, H1, main-landemerke og radiospiller kommer fra temaet.
- `[rr_player_matches]` delegerer til spillerpluginets eksisterende `[rr_spillerkamper]`. Ingen spillerdata, importer eller privat profilinformasjon flyttes til temaet. Manglende plugin og tom datamengde får egne lesbare beskjeder.
- Kampvalget følger eksisterende `rr_poll_selected_match`. Redaktøren trenger ikke velge kamp i to systemer. Ikke legg `rr_match` i pilotsidens URL: dette navnet kolliderer med WordPress' eksisterende posttype-spørringsvariabel. Den gamle `/dagenskamp/`-ruten håndterer fortsatt sin opprinnelige parameter.
- Bruk kortkoden direkte i sidens lagrede innhold, én gang. Synkroniserte mønstre, maldeler, arkivløkker og utdrag støttes ikke av forberedelsen i denne første versjonen.

## Pluginets ansvar og grenser

`inc/match-day-embed.php` forbereder public-visningen før temaoutput. Dette gjør at cache-headere kan settes før personlige sesjonstokens rendres. Kun GET på en ubeskyttet, enkeltstående side med kortkoden forberedes. API- og speaker-parametere fjernes midlertidig ved innbygging og gjenopprettes etterpå. En POST til innholdssiden utfører ikke kampmodulen. Kortkoden returnerer bare den forberedte visningen i hovedløkken og én gang, slik at faste HTML-ID-er og lyttere ikke dupliseres.

Alle handlinger går fortsatt til `/dagenskamp/` og eksisterende API med de samme nonce-, rolle-, kandidat- og stemmekontrollene. Vipps/utlogging går fortsatt via kanonisk kampside; retur til pilotsiden er ikke implementert. Den gamle modulens eventuelle oppdateringer ved visning beholdes, inkludert eksisterende kåringsregler. Dette er et presentasjonsadapter, ikke en ny stemmemotor.

Modulen beholder inline-stiler og skript fra dagens løsning. En senere oppdeling kan flytte disse til pluginets assets etter egne regresjonstester. Første pilot endrer ikke datalagringen.

## Drift som ble oppdaget

Produksjonens aktive kampfil var nyere enn migreringsbroen: innloggingsstatus, tidsavbrudd, sesjonsoppfriskning, prosentvis resultatvisning og tidligere premie-unntak manglet i broen. Den lokale, tidligere verifiserte kampfilen med kompakt logo ble sammenlignet med **lesing** av produksjonens temaeditor: 101283 UTF-16-kodeenheter, FNV-1a 995426637. Den matchet før adapterendringene. Ingen lagring i produksjonens editor ble utført.

Oppdatert kampfil beholder migreringsbroens lokale klubb-logoer og klokke-CSS. Det gamle SOURCE-MANIFEST beskriver fortsatt det opprinnelige septemberøyeblikksbildet; denne rapporten dokumenterer den nyere kampfilens proveniens.

Spillerwidget 1.3.0 ble installert bare i den lokale kopien fra grenen `fix/player-profile-and-match-followup` (`b01e112a7c8c5c8df5273bfc0093e8601171bb0b`). Den har ingen visningsklare spillerkamper i denne kopien; tomtilstanden er kontrollert, men ferske spillerdata er ikke verifisert. GitHub-grenen er ikke automatisk bevis for identisk produksjonsfilinnhold.

## Verifikasjon

- 25/25 HTTP-kontroller på faktisk lokal WordPress: visning, cache, innlogget/utlogget, query-isolering, POST-grense, nonce/roller, gyldig syntetisk stemme, dobbelstemme, pause og 85-minutters stenging.
- 10/10 isolerte adapterkontroller: rendergrenser, duplikat, manglende spillerplugin, tomtilstand og uendret delegering.
- Fire nettleserbredder: 320, 390, 768 og 1440 px. Ingen horisontal overflyt, ett main/H1/stemmeskjema, seksjonsankre med minst 44 px høyde, tastaturanker og ingen JavaScript-unntak. Nettleseren venter på API-oppdatert stemmestatus.
- 54 sidemalkontroller og 38 eksisterende forsidekontroller består; PHP/JS/JSON/presentasjonsgrense og fire ZIP-pakker er kontrollert.
- GitHub Actions var fortsatt ventende eller avbrutt før teststeg startet ved denne leveransen. Lokal grønn status erstatter ikke grønn CI.

Testkontoene er syntetiske. Kun den reserverte testkampen 99999992 ble brukt. Testresultater i `football-pilot/` inneholder ingen legitimasjon. Seeder krever WP-CLI, lokalt miljø, eksplisitt testflagg og localhost, nekter å overskrive eksisterende fixtures og lagrer opprinnelig kampvalg i privat fixturefil. Behold testdata for inspeksjon; full retur gjøres ved å gjenopprette privat stagingbackup eller reversere kun oppføringene fra fixturefilen.

## Før godkjenning for produksjon

1. Integrer foreldre-PR-ene i riktig rekkefølge (#28 → #53 → #60 → denne piloten), håndter konflikter og få grønn CI på samlet kode.
2. Prøv på oppdatert, tilgangsbegrenset staging med riktige pluginversjoner og kontrollerte spillerdata. Gjenta mobil, cache/CDN, beskyttet innhold og avstemningstester.
3. Test ekte Vipps-retur, utlogging/innlogging i samme nettleser og radiospiller på fysisk mobil. Den lokale kopien blokkerer ekstern HTTP, e-post og lyd med nettverksisolering og CSP; disse tjenestene er ikke ende-til-ende-verifisert her.
4. Avklar og test menylengde, faktiske redaksjonelle tekster, kampvalg og at samtidige brukere aldri får hverandres status/token fra cache.
5. Produksjonsaktivering og deploy er en separat beslutning. Ingen aktivering, datamigrering eller publisering skjer automatisk ved innsetting av dette mønsteret.
