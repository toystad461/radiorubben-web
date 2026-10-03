# Fotballrobot 0.9.1 – drift fra 3. oktober 2026

## Gjeldende status

0.9.1 er aktiv på https://www.radiorubben.no. Autentisert GET
`/rr-fotballrobot/v1/automation` bekreftet regelversjon 1.0.0, 3600 sekunders
forsinkelse og enabled=true etter utrulling.

Produksjonen er publisert fra commit
`d43ec970182baeda8f84ad916c9dcf2bd8036d8c` i PR #37.
GitHub-baseline `aba73e7416e9d061809f3bf83c03c8ce14a7bd28` er en fersk
kopi av den installerte 0.9.0-pluginen, hentet via eksisterende SSH-tilkobling.
PR #29/#36 er ikke endret. Main og den brede WordPress-deployen er ikke brukt
til utrullingen; bare den kontrollerte pluginmappen er oppdatert.

## Flyt og tidspunkt

- Nye bekreftede kampslutt i dashboardarkivet oppretter én jobb per kamp.
- Første steg får due_at = confirmed_at + 3600 sekunder. En eldre køhendelse
  kan ikke starte tidligere enn denne fristen.
- Den eksisterende ChatGPT-oppgaven `6aac6303fe208191978a1d7d74290514`
  heter nå «Klargjør Bremnes-kampomtaler». Den sjekker hver time i Europe/Oslo,
  og bruker den autentiserte køinngangen for nye bekreftede kamper som ikke
  fanges av dashboardet, særlig bortekamper.
- Mangler kilden dokumentert sluttid, brukes første registrerte bekreftelse.
  Ingen slutttid beregnes fra avspark eller siste hendelse. Timevis observasjon
  kan dermed oppdage sluttsignalet senere. Trafikkavhengig WP-Cron kan også
  kjøre et forfalt steg senere; dette er ikke en garanti om artikkel nøyaktig
  60 minutter etter det faktiske sluttsignalet.
- Forberedelse → skriving → faktakontroll → språkvask → eventuell ny
  faktakontroll → utkast. Hvert kontrollsteg for kampreferater gjør høyst ett
  modellkall.
- Manuell sluttgodkjenning beholdes. Kvalitetsavvik lagres med sperre og funn.
  Ingen automatisk ny betalt skriving ved feil.
- Oppgaven skriver/publiserer ikke lenger via den gamle generiske post-ruten.
  Den krever aktiv 0.9.1 og 3600 sekunder før den kølegger.
- Seniorlag 30365 og 48835 er omfanget for denne eksisterende oppgaven.
  Ungdoms-/ukeplanarbeid i andre PR-er er ikke aktivert her.

## API

Alle ruter krever eksisterende administrator- og redigeringstilgang.

| Rute | Formål |
| --- | --- |
| GET /rr-fotballrobot/v1/automation | Aktiv versjon, språkregler, forsinkelse og aktivering |
| GET /rr-fotballrobot/v1/matches/{id}/queue | Jobbstatus og eksisterende utkast, uten review-token |
| POST /rr-fotballrobot/v1/matches/{id}/queue | Uavhengig bekreftet kampslutt, med confirmed_finished=true og eksakt NFF source_url |
| POST /rr-fotballrobot/v1/quality/{post_id} | Kontroller lagret tekst etter manuell redigering |

Køinngangen gjenbruker jobber, finner gammel ordinær slug, respekterer
papirkurv og binder bekreftelsen til kamp-ID, lag-ID-er, dato og resultat.
Tidsavbrudd må følges av statuslesing før nytt kall.

## Verifisering

- [Isolerte tester og lint](https://github.com/toystad461/radiorubben-web/actions/runs/37107647158): bestått.
  231 tallfestede kontroller samt rapport- og pluginoppstartskontroller.
- [WordPress-/modellprøve](https://github.com/toystad461/radiorubben-web/actions/runs/37107783707):
  kandidatkode utenfor webroten lastet i en separat native WP-CLI-prosess,
  med installert robot hoppet over. Brukte eksisterende database og modellnøkkel,
  og opprettet bare et separat testutkast. Dette var ikke en separat stagingdatabase.
  E-post og uvedkommende HTTP-kall var blokkert i prøveprosessen.
- Testkamp 8985496: Smørås–Bremnes, 1–3, 01.10.2026. Kilde kontrollert på NFF.
- [Testutkast 1071](https://www.radiorubben.no/wp-admin/post.php?post=1071&action=edit):
  «Bremnes scoret tre på sju minutter». Status draft, regelversjon 1.0.0,
  quality_passed=true, tom funnliste. Egen _rrfr_test_only-sperre hindrer publisering.
  Eksisterende offentlig kampartikkel er beholdt.
- [Selektiv utrulling](https://github.com/toystad461/radiorubben-web/actions/runs/37108010279):
  eksakt samme plugin som besto prøven; produksjonsfiler verifisert mot SHA-256
  før endring. Privat kodebackup og automatisk tilbakeføring ved feil.
  Ingen tema, andre plugins, hemmeligheter eller øvrige PR-er erstattet.
- Etter utrulling ble 0.9.1 aktiv, riktig runtime, gyldig kontrollmetadata,
  testpubliseringssperre, REST-helse og avsluttet vedlikeholdsmodus verifisert.
- Visuell gjennomgang i WordPress-redigereren er ikke gjort. Lagret og rendret
  utkastinnhold er lest via WordPress API. Kvalitetskontroll erstatter ikke
  redaktørens faglige vurdering.

## Tilbakeføring

Backup finnes på serveren:
`~/.radiorubben-deploy/backups/fotballrobot-d43ec970182baeda8f84ad916c9dcf2bd8036d8c/code.tar.gz`.

Tilbakefør bare Fotballrobot-koden og fjern de tre nye filene som
utrullingsskriptet lister, dersom full rollback blir nødvendig. Utkast og
faktametadata skal beholdes. Stans kølegging dersom 0.9.1 ikke er aktiv;
automatikkens versjonskontroll gjør dette uten gammel publiseringsfallback.
Ikke kjør de engangs, branchbundne preflight-/release-jobbene på nytt som
en generell deploymekanisme. Neste release må få ny baseline, konkret
kandidatprøve og eksplisitt valgt kildeversjon.

## Avgrensning mot tidligere dokumentasjon

EDITORIAL-RULES.md beskriver opprinnelsen i PR #36. Dens gamle avsnitt om
0.6.0 og «ikke deployet» gjelder denne opprinnelige PR-en, ikke driftsstatusen
beskrevet her. Produksjonens Microsoft 365-transport, FactStore og øvrige
0.9.0-funksjoner er bevart. Fotballdata-adapteren fra PR #29/#31 er ikke
automatisk lagt over produksjonsparseren.
