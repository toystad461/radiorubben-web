# Produksjonskontroll – måling, kodegrunnlag og åpne sperrer

Dato: 8. oktober 2026. Koordinerende sak: Web #67; Studio #52.
Dette supplerer `PRODUCTION-RECONCILIATION-2026-10-08.md` og
`production-candidate.json`. Det er IKKE en ferdig produksjonsgodkjenning.

## Utført i denne oppfølgingen

- Web #67 og Studio #52 er lest på nytt. Begge er åpne kladder, uten merge.
- Den separate testarbeidsflyten `news-priority.yml` manglet i #67, selv om
  testfilene var med i det importerte theme-next-treet. Den tas inn fra
  #66-commit `d7afe130faf55a7907a039df8cefb9adea436434`, blob
  `df9eab70d0c4a2f91c49f48e6dc483d84b4d1c95`. Den kjører isolerte PHP-
  kontrakter og åtte syntetiske kombinasjoner av skjermbredde/tekststørrelse.
  Ingen publiseringsworkflow eller SSH-operasjon følger med.
- `compare_code_inventory.py` sammenligner kun lokale, allerede autorisert
  innhentede SHA-256-manifester. Det leser ikke nettstedet eller databasen,
  henter ingen hemmeligheter og starter ingen jobb på en server.
- 24 isolerte enhetstester består lokalt. Ny CI kontrollerer også at de fire
  importerte kodeområdene fortsatt har identiske Git-tre-ID-er som kandidaten.
  Fersk GitHub-CI skal knyttes til eksakt ny head i PR-en; tidligere CI teller
  ikke som test av denne oppfølgingen.

## Kontrollstatus og krav for å lukke punktene

| Område | Status ved kontrollen | Krav for fullføring |
|---|---|---|
| Samlet Next/Site Functions/robot-kilde | Samlet kandidat, ikke full live-kopi | Nye kontroller og målt identitet for alle forvaltede filer |
| Nyhetsforsidens egen testjobb | Manglende jobb gjeninnføres | PHP-kontrakter og åtte syntetiske skjermtester på ny head |
| Fotballrobot 0.10.5 | Uavklart referanse | Aktiv versjon, faktisk filsett og tilhørende leveringsbevis |
| Spillerwidget | Aktiv kildeversjon uavklart | Ferske kodehasher, ruterespons og dokumentert jobb-eierskap |
| WordPress-tilkobling | Ingen nettsted registrert i WPVibe | Brukerens godkjenning av ny tilkobling, deretter autentisert lesekontroll |
| WordPress databasekode/mu-plugins | Ikke ferskt kontrollert | Liste over aktive kodekilder og hasher, uten private verdier |
| Studio selective-state | Historisk publisering kjent, fersk tilstand mangler | Autentisert inventering mot gjeldende state og klassifisering av bevarte forskjeller |
| Publisering fra main | Potensiell endring ved merge | Avklar/avgrens gjeldende automatikker før merge; ingen generell apply |
| Cron og doble motorer | Ikke ferskt kontrollert | Eierskap, tidsplan, siste vellykkede kjøring, deduplisering og køstatus |
| Innlogging og redaksjonsflyt | Ikke ende-til-ende-testet nå | Korrekt bruker og avvist feil bruker; godkjenning må fortsatt kreves |
| Backup og tilbakeføring | Ingen ny gjenopprettingstest | Privat, lesbar kopi og isolert gjenoppretting uten gamle data over produksjon |
| Hele produksjonen | IKKE ferdig avstemt | Alle punkter har ferskt belegg; ingen ukjente kilder overskrives |

Søk etter `0.10.5` i Web-commitmeldinger ga ingen treff. Dette er ikke bevis
på at koden eller en serverendring ikke finnes. Den nyere refen
`fix/player-profile-and-match-followup` på `356a110d...` har pluginheader 0.9.7;
den kan ikke brukes til å løse 0.10.5-referansen eller erstatte 0.10.4.

Den tidligere blokkerte nye SSH-inventeringen er ikke forsøkt på nytt eller
omgått med en annen kjørevei. Ny WordPress-godkjenningslenke er klargjort,
men tilkoblingen regnes ikke som gjenopprettet før godkjenning og faktisk lesing.
Denne koblingen gjelder WordPress; den løser ikke alene Studio-private-inventeringen.

Forsøk på anonyme HTTP-kontroller fra arbeidsmiljøet ga tilkoblingsfeil.
Nettleserverktøyet viste dessuten eldre indeksert forsideinnhold. Ingen av
resultatene brukes som bevis for dagens driftsstans eller dagens tema.

## Lokalt manifestformat

Én eksplisitt kodekomponent per sammenligning, for eksempel `next-theme`,
`site-functions`, `fotballrobot`, `player-widget` eller `studio-managed`.
Dette er et formatkrav, ikke en oppskrift på å omgå en stoppet innhenting.

Forventet manifest har `schema_version: 1`, `scope`, full `source_commit`
og `files`, en ikke-tom liste av objekter med kun `path` og `sha256`.
Observerte data har samme `schema_version` og `scope`, i tillegg til
`observed_at` med tidssone, `complete: true`, `errors: []` og `files`.
Filstier er relative til den avtalte kodekomponenten. Begge lister må være
komplette innenfor samme avgrensning. Manglende kataloger, uleselige filer
eller symlenker må registreres som innhentingsfeil, aldri skjules ved å
utelate dem. Autentisitet og avgrensning må vurderes separat av operatøren.

Ingen filinnhold, passord, tokens, databasekopi, konfigurasjon, brukerdata,
opplastinger eller køer skal inn i manifestene. Kodebiter i database og
cron krever egne dataminimerte bevis og skal ikke presses inn i dette formatet.

Kjør bare mot lokale manifeste filer:

```sh
python3 scripts/test_code_inventory.py -v
python3 scripts/compare_code_inventory.py expected.json observed.json
```

Exit 0 = det oppgitte kodeområdet matcher et komplett manifest som er høyst
24 timer gammelt. Exit 1 = avvik/ufullstendig/for gammelt. Exit 2 = ugyldig
input. Et kortere intervall kan angis med `--max-age-hours 1` til `24`.
Fremtidige tider, duplikater, farlige stier, private datastier, ukjente
filfelter og ugyldige hasher avvises. Ingen toleranse som skjuler faktiske
byteforskjeller eller utelater ekstra filer.

Resultatet sier alltid `deployment_authorized: false`. Selv en perfekt
filmatch beviser ikke at innlogging, radioavspilling, avstemning,
redaksjonell publisering, cron, database eller gjenoppretting fungerer.

## Endringsgrense

Denne oppfølgingen endrer kontrollverktøy, isolert CI og dokumentasjon på
oppryddingsgrenen. Eksisterende runtime-filer, main, tilgangsnøkler,
WordPress-innhold, Studio-data og produksjonsworkflows er ikke endret.
At CI har `contents: read` betyr ikke at den ellers aktive main-pipelinen
har blitt deaktivert. Det er fortsatt ingen autorisasjon til fullpakke-deploy.

GitHub er kildemaster. En driftskopi kan lagres i verifisert OneDrive-mappe,
med lenker til de to PR-ene og tydelig status. Opplasting må kvitteres separat;
ingenting i denne filen hevder at automatisk OneDrive-synk finnes.
