# Kampbakgrunn, spilletid og skjulte navn – testkandidat

Arbeidsområde: System & Kvalitet / Lokalt Engasjement / Manus & Stikk.
Base: feature/bremnes-youth-weekly, b8be53ae19fdb5c4be37c97153a8c5019a4a14a0 (PR30).
Dette er en separat kandidat, ikke en produksjonsavstemming eller utrulling.
Main mangler hele roboten. PR101 er en separat recovery-testpakke; dens historiske
manifest skal ikke endres for denne kandidaten. Ingen endring i TTS-godkjenning.

## Læring og faktisk innhenting

BT-eksempel: https://www.bt.no/lokalfotball/i/77OLv9/bergensdalen-med-hjemmeseier-over-austevoll-2
Kampreferanse: https://www.fotball.no/fotballdata/kamp/?fiksId=9188994
Eksemplet inspirerer struktur, ikke faktagrunnlag for nye saker. Full BT-artikkel er
ikke brukt eller kopiert. NFF-kampen lot seg ikke hente på nytt i denne økten;
syntetiske scenarier brukes i testene, uten påstand om verifisert live-kamp.

1. Start med kamp-ID og kontroller lag-ID, turnerings-ID, dato og resultat mellom
   eksisterende Fotballdata-adapter og NFF-kampdetaljene.
2. Ungdomsflyten henter nå begge lagenes historikk gjennom eksisterende
   `Fotballdata::history(teamId)` (`teams/{id}/matches`). Ingen bredt navnesøk.
3. `Facts::form` avgrenser til riktig lag, turnering, sesong og før avspark.
   Hjemme, borte og samlet form er separate utvalg. Uavklarte kamper bryter
   beregningsgrunnlaget; duplikater telles ikke, motstrid avvises.
4. `win_evidence` inneholder kilderader med kamp-ID, kilde-URL og resultat.
   Kildeoversikten har hentetid, lag-ID og formål. Faktapakken inngår i eksisterende
   snapshot og kontrollhash. Feil i historikk gir en advarsel og ingen formpåstand.
5. Vinkler får belegg-ID-er. Siden adapteren ikke beviser at historikken er komplett,
   skal teksten begrenses til registrerte kamper i kontrollert historikk.
6. Skriver, faktakontroll, språkvask og eksisterende redaktørgodkjenning brukes videre.
   Denne endringen lager ingen ny Studio-visning; belegg ligger i faktapakken.

## Spilletid

Primær referanse levert av Thomas og kontrollert 22.10.2026:
https://www.fotball.no/kretser/hordaland/dommer/abc-for-dommere/#229229

| Klasse | Spillform | Ordinær spilletid |
| --- | --- | --- |
| 13 år | 9er | 2 × 35 minutter |
| 14 år | 9er / 11er | 2 × 35 minutter |
| 15–17 år | 11er | 2 × 40 minutter |
| 19 år | 11er | 2 × 45 minutter |
| 13–19 år | 7er, også i 11er-serie | 2 × 35 minutter |
| Senior | 11er | 2 × 45 minutter |
| Senior / old girls-boys / veteran | 7er | 2 × 30 minutter |
| Veteran over 60 | 7er | 2 × 25 minutter |

Siden har egne turneringsbestemmelser: Ballspark-cupen er 2 × 30 minutter;
Hordalandscupen bruker seriespillets spilletid. Nasjonale konkurranser har egne
bestemmelser. Tabellen er referanse, ikke automatisk fallback fra lagnavn.
Før slik fallback aktiveres må adapteren dokumentere krets, sesong, faktisk
spillform, klasse og eventuelle turneringsunntak. Dagens adapter mangler dette.

Kandidaten leser eksplisitt «Spilletid» fra kampkilden (total eller 2 x omgang).
Manglende/motstridende verdi gir ukjent, aldri standard 90. Sen utligning kan
foreslås i siste ti ordinære minutter når hendelsessummen stemmer. Dette er en
redaksjonell terskel, ikke en NFF-regel. Minutt er ikke bevis for faktisk sluttid.
Ingen endring i scheduler, pauselengde eller automatisk publisering.

## Skjulte navn

Skjulte navneplassholdere blir tomme før generering. Mål, lag og minutt bevares.
Vinkelen bruker lagets navn. Ingen identitetsleting eller antakelse om alder.
G13 er en klasse, ikke dokumentasjon på hver spillers alder. Manglende spillerdata
skal ikke fylles inn fra andre kilder. Uklar lagoppstilling beholdes som ukjent.

## Verifisering

Kjør `python3 scripts/test-fotballrobot.py` fra repoets rot på utviklingsmaskin/CI
med PHP, DOM og mbstring. Den finner også `tests/context-learning.php` automatisk.
Eksisterende negative tester for publiseringsgodkjenning og endret grunnlag beholdes.
GitHub-baselinejobben kjører på PR-er mot denne feature-basen. Lokal maskin mangler
PHP. Første GitHub-kjøring 39837928779 bestod 63 PHP-syntakskontroller, alle 27
isolerte suiter og mobil/desktop-kontroll av godkjenningsflaten. Bare blanklinjer
ved filslutt feilet; de er rettet i oppfølgingscommit. Endelig CI-status står i PR103.
Dette er ikke en live API-, modell- eller VPS-test.

Før test-VPS: separat godkjenning, ferskt serverfilmanifest, konkret differansepakke
og kontroll av isolasjon. Før produksjon kreves egen gjennomgang og autorisasjon.
