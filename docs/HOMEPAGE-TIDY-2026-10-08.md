# Forsideopprydding – 8. oktober 2026

Kildegren: `codex/homepage-tidy-20261008`, basert på PR #67 / `210461622b790980fb57d34d3f12b53212bb3a51`.

## Fersk, avgrenset kildeavstemming

WPVibe bekreftet aktivt tema `radio-rubben-next` 2.0.0-rc.5. Et nytt utkast ble klonet fra dette temaet; ingen filer ble redigert på WordPress. Syv forside-/menyfiler ble lest fra den ferske klonen og lagret i commit `14995a0`. De inneholder allerede publisert sidefelt, vær, medlemslenke og enklere meny, som manglet i PR #67. Den offentlige DOM-en bekreftet oppsettet.

Leseverktøyets linjenumre er fjernet, og Git normaliserer linjeskift. Dette er kildeavstemming av syv filer, ikke byteidentiske serverhasher eller en full produksjonsinventering. Øvrig tema, plugins, driftsdata og åpne kontroller i PR #67 er ikke avstemt her. Eksisterende lokale ZIP-er er ikke brukt som produksjonsfasit.

## Visuell endring

Et separat stilark for klassisk forside gir jevnere avstander, tilpasser overskrifter og nyhetskort til hovedkolonnen med sidefelt, samler kortradier og viser tydelig tastaturfokus. Mobil beholder nyhetene før sidefeltet. Ingen ny skjuling av nyheter, kampdata eller værinformasjon.

Kun `front-page.php` og `assets/css/homepage-refinement.css` er nye utrullingskandidater mot dagens leste WordPress-grunnlag. De øvrige runtimefilene i første commit dokumenterer det eksisterende oppsettet. Historiske manifesttre i `production-candidate.json` beholdes i `assembly`; `review_source_trees` fastlåser denne kladdens nye Git-tre. CI sjekker kladdens tre og fortsatt uendret Site Functions/wordpress. Ingen historiske serverhasher er endret.

## Kontroll og publisering

Eksisterende nyhetskontrakt er oppdatert for det publiserte sidefeltet og stilrekkefølgen. GitHub CI kjører PHP-, kilde- og syntetiske skjermkontroller. Lokal og GitHub teststatus rapporteres i PR-en; ingen grønn status skal antas før kjøringene er ferdige. Full visuell kontroll av ekte WordPress-utkast på mobil og desktop gjenstår.

Status ved første levering var uten publisering. Etter brukerens «publiser» og bekreftelse av fersk full backup ble de to filene klargjort i WPVibe-utkastet, inspisert og publisert selektivt via GitHub 8. oktober kl. 13:15:56 Europe/Oslo. Ingen main-merge, temabytte, innholdsendring eller pluginendring. Produksjonsavstemmingen i PR #67 beholdes separat. Se publiseringskvitteringen nedenfor.

## Publiseringskvittering

- Runtimekilde: `e0e04ba79fcfc47dd27a0e3880325ae18cfaa642`.
- Utrullingscommit: `6941f141b0507b126c219052a5315066b29c1013`.
- Vellykket jobb: https://github.com/toystad461/radiorubben-web/actions/runs/37768862753
- Endret filsett: `front-page.php`, `assets/css/homepage-refinement.css`.
- Gjeldende aktivt tema og nettstedets home-URL ble kontrollert før skriving. Webhotellets webroot ble kanonisk løst med PHP til `/customers/9/3/1/cptk37ymg/webroots/r1417157`; mål var dens `wp-content/themes/radio-rubben-next`.
- Fersk serverhash før endring, `front-page.php`: `bdf1a7ed6014d90fd9cc3882ea35d4ac0deb8df9adc0ee00736bf1f6e6bfe281`. Kilden samsvarte med commit `14995a0` etter normalisering av CRLF og avsluttende blanklinjer. Originale serverbytes ble sikkerhetskopiert og sammenlignet med originalen; normalisering brukes ikke på backupen.
- Hash etter endring, `front-page.php`: `73c48657a83316e3617eda9289d0dcdddac050a441ceb24e74b146c5a45c364c`.
- Hash etter endring, CSS: `c9621e8b27b91baff33e021373d8e0c78c4a3976b3f3723f1ab47c34d51337c7`.
- Privat kodebackup: `/home/cptk37ymg_w1417156/.radiorubben-deploy/backups/homepage-refinement-37768862753-1`. Den inneholder original entry point og dokumentasjon av at CSS-filen ikke fantes. Brukeren bekreftet i tillegg fersk full nettstedbackup.
- Alle øvrige temafilers SHA-256 samsvarte før/etter. CSS ble installert før entry point via rename på samme filsystem. Automatisk feiltilbakeføring bevarer samtidige endringer ved å avvise overskriving ved hashavvik.
- Tilbakeføring ved behov: verifiser at publiserte filer fortsatt har etter-hashene, gjenopprett backupens `front-page.php` med samme-filssystem-rename, fjern kun CSS-filen som var fraværende før, og kontroller offentlig rendering. Ingen databasegjenoppretting trengs for disse filendringene.
- Offentlig rendering, nyheter, sidefelt og avspiller ble kontrollert i jobben. Direkte levert CSS samsvarte byte-for-byte med GitHub-kilden. Ekte Chrome uten utkastbanner bekreftet nye desktopstilverdier (28px intropadding, 24px kolonneavstand, 18px kortpadding), og mobil 390px hadde én hovedkolonne, 12px kortavstand og ingen horisontal dokumentoverflow.
- Tidligere forsøk stoppet før filendringer på mappekontroll, manglende `realpath` og ulikt antall avsluttende blanklinjer. De erstattes som utrullingsbevis av den vellykkede jobben over.

WPVibe-utkastet beholdes; publiseringen erstattet ikke hele temaet eller lagrede Site Editor-tilpasninger. GitHub-PR-en er fortsatt separat fra main og den bredere produksjonsavstemmingen.
