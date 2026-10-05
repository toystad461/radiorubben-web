# Selektiv RSS Studio-release – 05.10.2026

Brukeren bestilte deployment og tester med ekte data. Studio PR #23–26 er
integrert. Denne transport-PR-en endrer bare tre private Studio-kodefiler:
news-script.php, case-workflow.php og newsroom.php. Ingen plugin, offentlig
Studio-side, config, nøkkel, bruker, innlogging, aktiv sendeliste eller
WordPress-artikkel inngår i installasjonen.

Eksakt Studio-kilde: 0612c6c650ac7a8b5e6857ac6e3fca2ab0c516f4. PHP 8.2/8.4 og mobiltest er grønne
på denne headen. Den eksisterende nøkkelen i web-repoet brukes som ved
tidligere selektive Studio-releaser. Studio er kildeautoritet.

Fersk preflight: Actions 37298147955, grønn. To aktive RSS-saker har web,
ingen har radio. Alle åtte undersøkte filhasher ligger i manifestet.
Baseline må samsvare med gjennomgått Studio-kode; drift stopper før skriving.
Artefakten valideres både mot SHA-256 og Git blob-identitet. Tre kodefiler
kopieres privat før atomisk erstatning per fil; caller newsroom.php sist.
Installasjonsfeil tilbakefører de tre filene. Prosessdrap kan kreve manuell
tilbakeføring. Dette er ikke atomisk utrulling av alle tre filer samtidig.

Backup: $HOME/.radiorubben-deploy/backups/rss-studio-<release-sha>.
Tilbakeføring: php <stage>/scripts/rss-studio-install.php <stage> <backup> rollback.
Bare de tre navngitte filene gjenopprettes. Kildecommit/before/after finnes
i backupens manifest.json og scripts/rss-studio-release.json.

Isolert ekte-test bruker Bømlo sin faktiske RSS og originalartikkel, aktiv
Studio-kode og eksisterende serverkonfigurasjon. Bare en privat test-sendelist
under stage skrives; ingen artikkel eller e-post sendes. Kontroller rapporteres
separat: generering, felles original, språk-/kildekontroll og fravær av
godkjenning/levering. Innhold og kildegrunnlag blir private; bare URL, ID-er
og statusverdier logges. Modellsvar kan kreve redaksjonell retting.
Testfeil etter vellykket installasjon rapporteres og gjentas ikke blindt.
Innlogget UI og faktisk sluttgodkjenning er separate kontroller.

## Avklart stopp før installasjon

Første release-kjøring 37298776074 besto artefaktkontrollen, men stoppet fordi
Uniweb sin /run/webroots-adresse er en aliassti. Ingen kode ble endret:
fersk kontroll 37298910886 bekrefter alle opprinnelige hasher og samme to
RSS-saker. Kanonisk app-sti er /customers/9/3/1/cptk37ymg/webroots/r1417157/studio-private/app.
Manifestet låser denne observerte, eksakte stien; målbanen og de tre filnavnene
er fortsatt faste. Ingen sertifikat-, rolle- eller nøkkelsperre omgås.

## Bekreftet installasjon og ekte-data-test

Release e145c19f0a12f4c5431e536bb7afb900a00765fe, [Actions 37299028081](https://github.com/toystad461/radiorubben-web/actions/runs/37299028081)
er grønn. Alle tre etter-hasher er bekreftet og privat backup opprettet.
Ekte Bømlo-original ble hentet, og begge utkast ble laget på én isolert sak,
med identisk kildeobjekt. Nettkontrollen besto; radiokontrollen kjørte, men
ga ikke klar-status. Ingen godkjenning eller WordPress-levering ble foretatt.
Hash av aktiv sendeliste før/etter var identisk. Testinnhold er privat.
Innlogget UI er ikke kontrollert her; Chrome-tilkoblingen var utilgjengelig.

Sluttkontroll [37299270971](https://github.com/toystad461/radiorubben-web/actions/runs/37299270971)
bekrefter de tre nye hashene og uendrede fem øvrige undersøkte avhengigheter.
Aktiv kø har fortsatt to eldre web-only-saker; ingen automatisk tilbakefylling
eller tekstendring er gjort. Isolert radiokontroll har needs_review, ingen
generelle issues og korrekt fingerprint. Ett segment er unsupported fordi
kontrolløren krever eksplisitt kildebelegg for opplysningen om at informasjonen
kommer fra Bømlo kommune. Sperren er beholdt; dette skal gjennomgås redaksjonelt
og er ikke omskrevet eller godkjent automatisk.

## Follow-up release and legacy completion — 2026-10-05

Studio source PRs [28](https://github.com/toystad461/radiorubben-studio/pull/28), [29](https://github.com/toystad461/radiorubben-studio/pull/29), [30](https://github.com/toystad461/radiorubben-studio/pull/30) and [32](https://github.com/toystad461/radiorubben-studio/pull/32) are merged after exact-head PHP 8.2/8.4 and mobile checks passed. Reviewed runtime source: `d9cdd6bd908155cb8359b42b3b357bc341279ffd`.

Fixes:
- Exact standalone source attribution is backed by a validated original URL and intact source snapshot; compound claims, wrong publishers and editorial issues still block readiness.
- Radio generation requests canonical Bokmål attribution.
- The reviewer uses strict structured output with exact segment count, bounded source references and string issues; server-side validation remains.
- A legacy web-only case can obtain a missing radio draft during recheck without rewriting saved web text or creating another case.

Successful selective release and bounded backfill: [Actions 37302374295](https://github.com/toystad461/radiorubben-web/actions/runs/37302374295). Release stage/backup suffix: `16670e6d2c91cd15fcbe4023ab06f73466ccd9fb`. Runtime backups are under `$HOME/.radiorubben-deploy/backups/rss-studio-16670e6d2c91cd15fcbe4023ab06f73466ccd9fb`; the pre-backfill active board is saved privately in the release stage as `active-board-before-backfill.json`. Preserve this board backup for investigation; never restore the entire board over subsequent editorial edits.

The fixed real-source test used Bømlo's “Bli med i frivilligheita”. Both productions share one item and exact original snapshot; both reviews completed. Radio passed, web required review for an unsupported statement about the project's goals. No approval or delivery was made, and the active board was unchanged by this isolated test. Test success means the pipeline and fail-closed editorial gates work; it does not assert every generated claim passes fact checking.

Both legacy cases were completed. Their IDs and original identity were preserved, web text remained byte-for-byte equivalent, and the newly generated radio and refreshed web check share one original. No case was approved or delivered. One radio check passed; the other radio and both old web texts require review. The old web texts contain unsupported claims about injuries, fire service attendance, official follow-up, road effects and frequency of landslides. Do not publish them until corrected and rechecked by the editorial workflow.

[Read-only postflight 37302620846](https://github.com/toystad461/radiorubben-web/actions/runs/37302620846) confirmed all eight dependency hashes, with the five unrelated files unchanged. It found **three active RSS cases with both productions, zero web-only cases**. The third case arrived through the ordinary automatic worker and passed both reviews. All three remain unapproved and have no delivery.

Earlier attempts stopped safely: malformed issue entries, too many evidence references, and an outdated Git blob in the transport artifact. These were diagnosed without blind paid retries and fixed before the successful release. No old case was changed until the final bounded backfill.

Remaining: browser verification of the authenticated Studio interface and retirement of the legacy robot.php/navigation after preserving its source exports. This release did not change public pages, navigation, credentials, WordPress plugin/theme or publishing permissions.
