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
