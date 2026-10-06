# Fotballrobot: bekreftet produksjonsgrunnlag

Kontrollgrunnlag for oppryddingen bestilt 6. oktober 2026.

Siste dokumenterte, vellykkede pluginutrulling er **0.10.4**, med runtime fra
`29e49e75b7890dd885050b4ad83d97ad266288fa`. Selektiv release
`dd6b775ee2f80365c3b55b123ae8f418c9a9eea1` kjørte i
[Actions 37243206615](https://github.com/toystad461/radiorubben-web/actions/runs/37243206615)
og fullførte 5. oktober kl. 01:17:48 Europe/Oslo.

Den rene branchen starter direkte på main
`9006e82e1ebc91e2a128cf2d3e263470e16db8e0`, som mangler hele pluginmappen.
Den innfører 32 uendrede runtime-filer, nødvendige tester, historiske
pluginveiledninger og denne avstemmingen. Ingen tema-, design-, widget- eller
Studio-runtime inngår i endringen. Ingen deploy, merge eller secrets-endring
er utført i oppryddingen.

## Hva som faktisk er bekreftet

| Trinn | Kilde og belegg | Betydning |
| --- | --- | --- |
| 0.9.1, PR #37 | `d43ec970…`, grønn release [37108010279](https://github.com/toystad461/radiorubben-web/actions/runs/37108010279), logg og historisk driftsdokument | Hel pluginmappe installert fra kontrollert 0.9.0-baseline; tester utelatt fra produksjon. |
| 0.9.2–0.9.9, PR #38–#49 | Selektive filsett, baseline-/målhasher og grønne releaser i [observations.json](evidence/observations.json) | Arvede filer spores til faktiske releaser, ikke til høyeste PR-nummer. |
| 0.10.1, PR #50 | Runtime `a253cfa3…`; CI 37237908379/37237905651; release [37237964444](https://github.com/toystad461/radiorubben-web/actions/runs/37237964444) | Aktiv WordPress-cron, samlevarsel og Studio-bro. Dette var et mellomtrinn. |
| 0.10.2/0.10.3, PR #51 | Releaser [37241518291](https://github.com/toystad461/radiorubben-web/actions/runs/37241518291) og [37241759848](https://github.com/toystad461/radiorubben-web/actions/runs/37241759848); runtime 0.10.3 `10b70edf…` | Bilde og separate rettelsesforslag ble faktisk installert. |
| 0.10.4, PR #52 | Runtime `29e49e75…`; CI [37243108895](https://github.com/toystad461/radiorubben-web/actions/runs/37243108895); release [37243206615](https://github.com/toystad461/radiorubben-web/actions/runs/37243206615); integrasjon 37243209317 | Nyeste bekreftede pluginruntime. Fire pluginfiler ble byttet selektivt. |
| Senere PR #54 | Grønne RSS-releaser 37302374295/37302620846 | Gjelder tre private Studio-filer; endrer ikke denne pluginruntime. |
| Senere PR #61 | Widget 1.3.1, release 37363419428 feilet før utførelse | Separat widgetarbeid; ikke inkludert og ikke regnet som aktiv Fotballrobot. |

PR #50–#54 er senere merget inn i sine stablede basegrener. Dette er ikke
belegg for utrulling til produksjon eller for at pluginen finnes på main.
De gamle PR-beskrivelsene kan dessuten vise eldre aktive versjoner.

Deploy-jobb 111555901247 i siste release viser eksakte etter-hasher for
pluginens hovedfil, `player-monitor.php`, `player-corrections.php` og
`newsroom.php`, samt `MOBILE_NEWSROOM_CODE_INSTALLED` og
`MOBILE_NEWSROOM_RUNTIME_OK`. Postflight bekreftet 11 køkort, bilde 813,
synlig rettelsesforslag, uendret publisert tekst/forslag og opphevet
vedlikeholdsmodus. [Den fastlåste readback-dokumentasjonen](https://github.com/toystad461/radiorubben-web/blob/a4e48d18b250e69d85be34be29451fcacfae0330/MOBILE-NEWSROOM-RELEASE.md)
bekrefter også autentisert HTTP GET med bilde og rettelsesmulighet.

Dette er en rekonstruksjon av **siste dokumenterte produksjonsgrunnlag**.
Det er ikke utført en ny fullstendig filkontroll på serveren i denne oppgaven.
Senere, udokumenterte serverendringer kan derfor ikke utelukkes.

## Deterministisk kontroll

Kjør fra repository-roten, uten nettverk eller WordPress:

```sh
python3 scripts/verify-fotballrobot-runtime.py
python3 scripts/test-fotballrobot-runtime.py
python3 scripts/test-fotballrobot.py
```

`runtime-manifest.json` låser alle 32 runtime-filer med SHA-256, Git blob-ID
og filspesifikt produksjonsbelegg. De innsamlede kvitteringene i `evidence/`
er hentet fra eksakte releasecommitter og er selv hashbundet. For
`includes/robot.php` brukes den uendrede kildefilen fra bekreftet 0.9.5-release
og dens vellykkede deploy-logg; den ble ikke skrevet ut som egen målhash i Git.

`runtime.sha256` består av UTF-8-linjer i stigende rekkefølge etter relativ
filsti: `sha256`, to mellomrom, filsti, LF. Ingen tidsstempler, maskinbaner,
filrettighetsmetadata eller arkivkomprimering inngår i samlet SHA-256:

```text
c84631aefaedf53f37b74f3c855b46921ad3bb7dcf36d1d0d9df879398df03a8
```

Kontrollen avviser endrede/manglende/ekstra filer, symlenker, uventet
kjørbar filmodus, dupliserte manifestposter og endrede belegg. Den kontrollerer
også støttematerialet, men tester og Markdown inngår ikke i runtime-hashen.
En separat lokal kopi med samme støttefiler kan sammenlignes via
`--plugin /absolutt/sti/til/plugin`. Dette er ikke en deploykommando.

Manifestet skal ikke oppdateres automatisk fra en vilkårlig arbeidskopi.
En fremtidig produksjonsbaseline må ha ny kildecommit, eksakt filsett,
vellykkede tester, release-resultat og readback. Nye funksjoner må fortsatt
skilles fra hva som er bekreftet aktivt.

## Tester og avgrensninger

- De 20 eksisterende PHP-testprogrammene beholdes. `tests/players.php` leser
  nå `completed-match.html` fra egen fixturemappe. Fixturen er byteidentisk
  med den som lå i widgetens tester på `29e49e75…`; widgetkode er ikke importert.
  Opprinnelige HTML-fixtures bevarer også kildeblanktegn og er unntatt
  whitespace-sjekken; alle filhasher kontrolleres fortsatt.
- Testkjøreren krever både riktig prosessresultat, forventet suksessmelding
  og fravær av PHP-feil. Dette fanger PHP-WASM som kan returnere exit 0 ved
  fatal testfeil.
- Lokalt: 50 PHP-filer syntakskontrollert og alle 20 suiter bestått med
  PHP-WASM 8.5.10. Manifestet og 11 negative/positive kontrolltester består.
- CI `fotballrobot-baseline.yml` kjører samme kontroller, native PHP og den
  eksisterende visningstesten ved 390/1280 px. Den har bare `contents: read`,
  ingen secrets, deployjobb eller ekstern WordPress-kontakt.
- Manuell sluttgodkjenning, kilde-/språkkontroll, samtykke til publisering,
  køer og varsler beholdes byte for byte i runtime. Ingen AI-kall, e-post,
  artikkelendring eller live godkjenning brukes som test.

## Drift og senere endringer

GitHub er kilde for koden. Privat konfigurasjon, Microsoft-/modelltilgang,
WordPress-data og de selektivt installerte Studio-modulene er eksterne
driftsavhengigheter og inngår ikke i denne kodekopien. Studio vedlikeholdes i
`toystad461/radiorubben-studio`. Eksisterende historikk og innstillinger skal
ikke erstattes av filer fra repositoryet.

Historiske engangs-deployskript er ikke importert. En eventuell senere
utrulling trenger et nytt, avgrenset manifest, ferske serverhasher, privat
backup og kontrollert tilbakeføring. Tilbakeføring av kode skal bevare
artikler, godkjenninger, køer og øvrige driftsdata.

Eksisterende `wordpress.yml` på main er uendret. Den kan kjøre deploy ved
push til main når repositoryets deployvariabler tillater det. En senere merge
må derfor vurderes separat; denne branchen og dens nye CI utløser ingen deploy.
Main er fortsatt uendret til en slik merge faktisk blir gjennomført.
