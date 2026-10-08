# Blokktema uten nye pluginlisenser

Status: 3.0.0-alpha.1, kun lokal testkandidat. Produksjon beholder 2.0.0-rc.5.
Dette er et ekte blokktema (`templates/index.html`), men ikke en ferdig migrering
av alle eldre visninger. Forsiden har bevisst en kompatibilitetsblokk.

## Redaktørens arbeidsflyt

1. Opprett en side og velg RR · Standard innhold, Bred funksjonsside,
   Redaksjonell eller Fotball / live.
2. Sett inn Radio Rubben-mønstre fra mønsterbiblioteket.
3. Søk etter «RR · Dagens kamp og avstemning» eller «RR · Bømlo-spillere ute»
   i blokklisten. Disse er navngitte varianter av WordPress sin kortkodeblokk;
   de er ikke nye datamotorer eller komplette visuelle forhåndsvisninger.
4. Kampmodulen bruker valgt kamp fra eksisterende plugin og skal bare settes
   inn én gang i selve sideinnholdet. Den støtter ikke plassering i synkroniserte
   mønstre, template parts eller sidefelt ennå. Behold kortkoden direkte i siden.
5. Header/footer redigeres under Utseende → Utforming. Navigasjonen starter med
   eksisterende hovedmeny (inkludert undermenyer), ikke en liste over alle sider.

WordPress core dekker blokkeditor, navigasjon, maler, mønstre og globale stiler.
Ingen Elementor Pro, ACF Pro eller annen betalt byggeplugin er nødvendig.
Eksisterende API-/strømmeavtaler og funksjonsplugins beholdes; dette er ikke et
løfte om at alle eksterne tjenester er kostnadsfrie.

## Struktur og ansvar

- `theme.json`: profil, bredder, typografi, fire registrerte sidemaler og deler.
- `templates/*.html`: 12 native maler, inkludert forside, arkiv, søk og 404.
- `parts/header.html`, `parts/footer.html`: redigerbare felles sidedeler.
- `patterns/block-*.php`: startinnhold; profil-URL og meny leses fra nettstedet.
- `inc/block-theme.php`: presentasjonsblokker for eksisterende forside,
  kampbanner og radioavspiller. Ingen nye API-ruter, tabeller eller cronjobber.
- `assets/js/block-editor.js`: editorbeskrivelser og kortkodevarianter.
- `assets/css/block-theme.css`: mobilramme og bredder for native blokkmaler.
- `rr-site-functions`: kamp, avstemning, sesjoner og andre eksisterende motorer.
  Ved innbygging legges kampens JavaScript i WordPress sin footer-kø; det må ikke
  passere blokkmalens tekstformatering, som kan gjøre `&&` om til HTML-entiteter.

PHP-applikasjonsruter beholdes og bruker nå samme blokk-header/footer. De gamle
PHP-sidemalene beholdes også: eksisterende `_wp_page_template`-verdier endres ikke
automatisk. Velg ny blokkmal eksplisitt på staging, side for side.

## Hva som gjenstår før produksjon

- Fullføre nettleserkontrollen etter rettelsen av JavaScript-køen. Før rettelsen
  passerte fire HTTP-maler, men nettleseren avdekket en scriptfeil. Syntaks alene
  er ikke nok som godkjenning av avstemning.
- Kontrollere lagring/gjenåpning av header, footer, navigasjon og alle fire maler
  i Site Editor. Database-lagrede maler kan overstyre filene fra GitHub: avklar
  hvilken som er fasit, og eksporter godkjente redaktørendringer før oppdatering.
- Bekrefte gjeldende spillerdata, eksisterende kamp-API, Vipps og Min side,
  RRLive, quiz, vær, søk/arkiv, passordbeskyttede sider og gammel maltilordning.
- Verifisere artikkelbyline, kilde-/fotokreditering, kommentarer og sponsorer
  før eksisterende artikler flyttes til ny single-mal. Kandidaten er foreløpig
  enklere enn den gamle artikkelvisningen på disse punktene.
- Dele overgangsforsiden opp i selvstendige redigerbare blokker/mønstre etter at
  funksjonsparitet er bekreftet. Bevar `rrpw_homepage` og fjernet footer-slagord.
- Teste tastatur, kontrast, mobilmeny, 320–1440 px og reell lydavspilling.
- Ta fersk backup og registrere aktivt tema, plugins og maltilordninger før
  eventuell produksjonsaktivering. Ikke importer lokale testbrukere/kamper.

## Teste lokalt

Bruk isolert WordPress på `127.0.0.1:8877`. `tests/block-theme-seed.php` avviser
andre verter og oppretter bare fire merkede lokale testsider. Kjør den med WP-CLI
`eval-file`, deretter `python3 theme-next/tests/block-theme-http.py`.
Eksisterende tester for kampgrenser, forsidehook og PHP-maler beholdes.

## Kilder

- https://developer.wordpress.org/themes/templates/templates/
- https://developer.wordpress.org/themes/global-settings-and-styles/introduction-to-theme-json/
- https://developer.wordpress.org/block-editor/getting-started/fundamentals/static-dynamic-rendering/
- https://developer.wordpress.org/block-editor/how-to-guides/curating-the-editor-experience/block-locking/
