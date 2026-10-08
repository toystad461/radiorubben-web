# Avgrenset publisering av Next-nyheter

## Publisert og etterkontrollert — 7. oktober 2026

Nyhetsplasseringen er publisert i det allerede aktive Radio Rubben Next-temaet.
GitHub Actions 37681748734 fullførte med success 20:24:45 UTC (22:24 Europe/Oslo).
Release: 37681748734-1. Utrullingscommit: 4354d0b5c1bbb6df4896b3a1ee3acc45fb75ccbb.
De tre runtime-filene er fra source-commit 332e443349a79d225f7b659ee22e798a831ef7df.
Ingen ny backup, temabytte eller endring av andre PR-er, plugins, innlegg,
bilder, innlogging, Studio eller OneDrive ble utført. PR66 er ikke merget;
den godkjente selektive GitHub-utrullingen er gjennomført uavhengig av merge.

Publisert rekkefølge: kompakt radio, Siste nytt, medlemsfelt, Sport fra Bømlo,
eksisterende spillerwidget og øvrig innhold. Tre nyheter og tre sportssaker.
Hovedsakens tittel står før bildet. Underkategorier og dobbeltkategorisering
kontrolleres: sport går foran nyhetskategori.

### Verifiserte serverhasher etter utrulling

- front-page.php: 090ed5771cc0dc8a5c17184933310dbe10b8a6197b5824b930fb770a3cdb012a
- template-parts/home/news-priority.php: 108ca933249567c10df0f5ca1de3369472a0376aa08cb00cd32fa52d5f16da31
- assets/css/news-priority.css: 7c6770359ee9ab37a151e4184ef23742abd0b57c64c43e740003d9f87fac5fc1

Alle øvrige eksisterende regulære temafiler hadde uendrede SHA-256-verdier etter
filbyttet. Bare hashverdier ble registrert, ikke kopier av produksjonsfilene.

Reell WordPress WP_Query-kontroll før filbyttet, uten plugins/tema og uten
skriving til databasen, besto:
- NEWS_POST_IDS=1271,1270,1269
- SPORT_POST_IDS=1091,1088,1073
- DOUBLE_CATEGORY_EXCLUDED_FROM_NEWS=303
- REAL_WORDPRESS_CATEGORY_CHECK=PASS

### Faktisk offentlig nettleserkontroll

GitHub Actions 37682127618 / head 84bd867f96554a44700822721d6b85e6496be72d:
**success**. Testen åpnet den vanlige offentlige forsiden uten innlogging eller
cache-parameter. Ikke syntetiske sider og ikke bare lokal HTML-rendering.
Kun GET/HEAD fikk passere i nettleseren; ingen avspilling, innlogging eller
publisering ble startet. Script: scripts/verify-next-news-public.py.

Fire Chromium-visninger besto: 320×720, 393×852, 852×393 og 1280×900.
Dokumentbredden var lik visningsbredden i alle fire. Ny CSS var aktiv,
radiodelen kompakt, tre nyheter og tre sportssaker synlige i DOM,
spillerwidgeten bevart og nyhetsoverskriften foran bildet. Ingen ufangede
JavaScript-feil ble registrert. Dette er ikke en fysisk iPhone/Safari-test.

Forsidens ordinære HTTP-respons var 200 og WP-Optimize meldte «not cached».
Ny rekkefølge ble også kontrollert via WordPress-tilkoblingen. Ingen manuell
cachetømming var nødvendig eller utført. Ingen lydstrøm er testet; eksisterende
pausevisning og deaktivert avspillingsknapp er bevart.

Alle 15 sportsinnleggenes fremhevede bilder er kontrollert via offentlig REST
mot tidligere registrerte ID-er, og er uendret. Sjekken endrer ikke innlegg.

Artefakt 11510475214, next-news-public-check: 8 skjermbilder og public-check.json.
Nedlastet ZIP er SHA-256-verifisert:
051861ec868bdc7c9a8fcb5fd1b448539476bb535db792670ad7dc53a860dbba.
Den faktiske mobilforsiden og nyhetsseksjonen er også visuelt gjennomgått.
Liten typografisk observasjon til videre finpuss: mobilens skjulte linjeskift
mellom «Ingen valg.» og «Bare» fjerner mellomrommet. Dette hindrer ikke
nyhetsvisningen, men bør rettes i en egen liten presentasjonsoppdatering.

## Autorisasjon og førkontroll

Thomas ba uttrykkelig om å rette publiseringsveien og utføre publisering,
og frafalt ny backup for denne avgrensede oppdateringen. Dagens Next var
allerede aktivt; det skulle ikke installeres eller aktiveres et annet tema.

GitHub-preflight 37681164715, kilde adf16bd7962fd33191031c60ecbecfb6be6a0d48,
besto mot den ekte produksjonsserveren. WP_DEPLOY_ENABLED=true og eksisterende
SSH-nøkkel/bekreftet vert fungerer. Environment wordpress-production og felles
concurrency radiorubben-wordpress beholdes; ingen permissions eller secrets er
endret og ingen godkjenningsgrenser omgås.

Aktivt stylesheet var radio-rubben-next; show_on_front=page. Frontfilens før-hash:
4f727fe11d63e64974b92ecd5a94ebd1c2fec3612497d584ef59461a89ddbf13.
Dette matcher nøyaktig Git-versjonen i f7cce8c4129e68361e65e86928aca71c24140026.
news-priority.php og news-priority.css manglet som forventet. Kategorier og
underkategorier ble kontrollert fra virkelig WordPress.

## Avgrenset utsendelse og tilbakeføring

Kun tre filer fra testet source-commit 332e443349a79d225f7b659ee22e798a831ef7df.
Commit-push på den eksplisitt godkjente PR66-grenen startet denne engangsveien;
ingen main-merge eller utrulling av det gamle temaet ble brukt for å trigge den.
Ingen ny database-/filbackup ble opprettet. Eventuell tilbakeføring bruker bare
den hashidentiske frontfilen som allerede finnes i Git, ikke en ny kopi av
serveren. Ny PHP-mal og CSS ble lagt først; frontfilen byttet sist med atomisk
rename. Før-hash og fravær av nye filer ble kontrollert under samme serverlås
som tidligere WordPress-publiseringer. Eksisterende forsidelayout-valg beholdes.

Skriptet stopper ved avvik, eksisterende lås, vedlikehold eller manglende
autorisasjon. Før-hashen gjør veien engangsavgrenset; en ny push etter vellykket
publisering kan ikke overskrive ukontrollerte serverendringer. Reell WP_Query
kontrollerer nyhets-/sportskategorier før filbyttet. HTTP-readback verifiserer
seksjonene, radiospilleren og spillerwidgeten. Privat staging inneholder
Git-kode og kontrollresultater, ikke en ny produksjonsbackup.

## Tester før publisering

De tre runtime-filene er uendret fra source-commitens tre grønne CI-kjøringer:
37675966368, 37675966215, 37675966082. De 24 PHP-kontraktene besto igjen i release.
Lokalt besto shell-syntaks, PHP-syntaks og fire isolerte deployscenarioer:
normal installasjon, før-hash-avvik, kollisjon med en ny fil og tilbakeføring
ved HTTP-feil. Testene bruker midlertidig filsystem og stubbet WP/curl, ikke
produksjonsdata. Utrullingen og etterkontrollene over er separate faktiske bevis.
