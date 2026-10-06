# Produksjon – 6. oktober 2026

Thomas ba uttrykkelig om å endre produksjonen og reinstallere manglende tema. Radio Rubben Next 2.0.0-rc.3 og Radio Rubben Site Functions 1.0.0-rc.4 er installert og aktivert på www.radiorubben.no. Det gamle temaet `radio-rubben-wordpress-v1` er beholdt for tilbakeføring. Ingen stagingdatabase eller testkontoer er kopiert til produksjon.

## Grunnlag

PR #62 var slått sammen i sin foreldregren etter at foreldregrenen var integrert videre. Piloten manglet derfor i main. Endringen er tatt inn over main i PR #64 (`release/next-production-20261006`). GitHub-testene fra forrige runde ble avbrutt uten teststeg; dette rapporteres ikke som grønn CI.

Nye pakker ble kontrollert lokalt: 134 PHP-filer, JS/JSON og presentasjonsgrense, 54 sidemalkontroller, 10 adapterkontroller og fire ZIP-bygg. Produksjonens kampfil samsvarte fortsatt med det verifiserte kildegrunnlaget: 101283 UTF-16-enheter, FNV-1a 995426637.

UpdraftPlus tok en fersk sikkerhetskopi av database, utvidelser, temaer, opplastinger og øvrige filer. UI bekreftet «Sikkerhetskopieringen er fullført» 6. oktober kl. 18:26:57. Kopien lagres på samme server, er merket for manuell sletting og ble ikke gjenopprettet som test. Det gamle temaet og dets innstillinger er fortsatt tilgjengelige; tilbakeføring skjer ved å aktivere det gamle temaet, ikke ved å overskrive fersk database med eldre kopi.

## Etterkontroll

Forsiden, Dagens kamp, Min side, Lytt, Kontakt, Neste kamp og Nyheter svarte HTTP 200 med Next-ressurser og uten synlig fatalfeil. Dagens kamp-API svarte HTTP 200; offentlig nyttelast før/etter var identisk bortsett fra servertidspunktet. Ingen kamp eller avstemning pågikk ved byttet. Ingen teststemmer ble avgitt i produksjon.

`/rrlive/` gir 404 fordi den eksisterende siden ID 835 er et utkast, ikke fordi temafilen mangler. Den ble ikke publisert. Spilleroversikten i den nye fotballsiden viste 12 spillere med de eksisterende plugin-dataene.

Temaets radioavspiller hadde tom strøm-URL og viste pause før og etter byttet. Faktisk lydavspilling er dermed ikke bekreftet. Full Vipps-innlogging med ny ekstern sesjon er heller ikke utført; eksisterende innlogget administratorvisning og anonym kampstatus er kontrollert. Disse begrensningene må ikke omtales som beståtte ende-til-ende-tester.

Fotballsiden ID 1235 er opprettet som utkast med `templates/football.php` og kortkodene fra mønsteret `combined-football-live`. Innlogget forhåndsvisning har ett H1, ett main-landemerke, én stemmemodul og 12 spillerkort; ingen horisontal overflyt ved 320, 390, 768 eller 1440 px. Publisering ble stoppet av automatisk godkjenningskontroll som ba om egen eksplisitt tillatelse til den nye offentlige siden. Tema-/pluginaktiveringen er fullført uavhengig av dette.

Cache-kommandoen bekreftet tømming av Rank Math sitemap-cache og objektcache. Det er ikke bevis for tømming av enhver eventuell ekstern CDN-cache; offentlige HTTP-kontroller viste nye temaressurser.
