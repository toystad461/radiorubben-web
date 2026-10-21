# Web – startpunkt for utvikling

Kontrollgrunnlag 21.10.2026: main `c349fc24dd8e6df588755680f1e16c87bf281204`.
Arbeidsområde: IT & Utstyr / System & Kvalitet / Lokalt Engasjement.

## Kodekart og valg av base

| Område | Plassering | Begrensning |
| --- | --- | --- |
| Statisk nettside | dist/, scripts/check.mjs, scripts/build.mjs | npm test/build kontrollerer denne, ikke WordPress |
| WordPress Next | theme-next/ | Tema, Site Functions og flere egne test-/pakkejobber |
| Eldre WordPress | wordpress/ | Eldre tema og plugins; ikke anta at hele treet er aktivt |
| Nyhetsbilder | wordpress/wp-content/plugins/rr-news-images/ | Avgrenset plugin og egen releaseflyt |
| Fotballrobot | Flere åpne PR-er | Hovedfilen mangler på kontrollert main |
| Quiz | RR_Quiz-plugin og weekly-quiz i to tema-/plugintrær | Bekreft aktiv inngang og scheduler før feilretting |

PR #101 har testpakken for Fotballrobot 0.10.8 på base #30; #30 bygger på
#63. #63 er historisk 0.10.4. Dette er ikke tillatelse til å merge eller
rulle ut hele kjeden. Les pakken/manifestet og avstem gjeldende serverkode.
#67 er en eldre produksjonskandidat med AGENTS.md som ikke er på main.
Denne rotinstruksen gjenbruker de varige avstemmingsprinsippene derfra;
fremtidig integrasjon av #67 må bevare oppdatert veiledning og dokumentere konflikt.

## Testvalg

Fra repoets rot på utviklingsmaskin/isolert CI (Node-versjon i validate.yml):

```sh
npm test
npm run build
python3 -m unittest discover -s theme-next/tests -p 'test_read_only_probes.py'
python3 scripts/news-images/test-package.py
```

For Next-endringer, følg `.github/workflows/theme-next.yml`:
`python3 theme-next/scripts/check.py`, `php theme-next/tests/poll-performance.php`
og `python3 theme-next/scripts/package.py`. PHP kreves. Bruk aktuelle
page-/home-/plugin-tester fra øvrige workflows ved berørte funksjoner.
Integrasjonstester som trenger WordPress skal kjøre på isolert testinstallasjon.

CI-ruleset 24734571 krever bare `kontroller`, som tester statisk nettside.
WordPress-, Next- og andre målrettede jobber finnes, men er ikke globale mergekrav.
Flere workflows filtrerer PR-base til main; en grønn kjede-PR kan derfor ha
mindre dekning enn forventet. Krev berørte tester på eksakt head i PR-en.
Anbefalt oppfølging: én entydig samlekontroll med relevant PHP/WordPress-dekning,
test også PR-er mot feature-baser og oppdater beskyttelsesregelen koordinert.

## Scheduler og utrulling

`fotballrobot-scheduler.yml` er eksplisitt aktivert og bruker et fast kilde-SHA.
De siste ti leste kjøringene 21.10.2026 var success. Det er bevis for jobbens
kontroller, ikke for at enhver kamp er behandlet eller en artikkel publisert.
Denne schedulerens `*/5`-plan gjelder fotball. Den beviser ikke quizfornyelse.
De ti observerte starttidene hadde omtrent 20–40 minutters mellomrom;
planlagt intervall må ikke brukes som målt leveringsgaranti.

Les riktig release-workflow og docs/WORDPRESS-DEPLOY.md før utrulling.
Main-push kan gi deploy avhengig av innstillinger. Ikke bruk gammel full
WordPress-apply for å erstatte dagens selektive installasjon.
Denne auditen endrer ingen miljøvariabler, cron, secrets, servere eller publisert innhold.

## Arbeidsflyt og kontekst

Oppgaven trenger repo, base/head, målmiljø, ønsket oppførsel, kildebelegg ved
redaksjonell logikk og observerbart ferdigkriterium. Hold kode i GitHub og
dokument-/produksjonsarkiv i OneDrive med verifiserte lenker.
Chat/Work kan avklare og produsere innhold; Codex skal lese denne koden og
instruksene. Ikke anta at tidligere samtaler er hele arbeidsgrunnlaget.
Ferdig betyr dokumentert kode/teststatus og separat deploy-/etterkontrollstatus.
