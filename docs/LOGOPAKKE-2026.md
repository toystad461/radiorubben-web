# Logopakke – kontroll og utrulling 5. oktober 2026

## Kilde og bevaring

Kilden er prosjektets `sources/RadioRubben-Logopakke.zip`, den lokale speilingen av den oppgitte `/mnt/data/RadioRubben-Logopakke.zip`. Sistnevnte sti finnes ikke på denne maskinen. Alle 44 pakkefiler er kopiert uendret til `wordpress/wp-content/themes/radio-rubben-wordpress-v1/assets/brand/2026-09/`, sammen med original ZIP og SHA-256-kontroller. Synkronisert original er urørt. Logoene er ikke tegnet på nytt.

## Funn før endringen

Offentlig HTML bekrefter aktivt tema `radio-rubben-wordpress-v1`. Header, radiofelt og footer brukte `uploads/2026/09/A1067EB6-D53D-4988-876B-5497FE466CD0.png`. Delingsbildet brukte en 1024×341-versjon av samme bilde. Favicon/Apple-ikon brukte `cropped-36690609-BD99-4CE5-BB21-D761DBCFEC5D` i 32/192/180 px. Rank Math organisasjonslogo brukte A1067-filen; forsidens primærbilde i strukturert data refererte dessuten til en eldre Black_Blue_White-logo fra november 2025.

Mediebibliotekets offentlige søk viste eldre `RR_Logo_Main_Dark`, `RR_Logo_Horizontal` og `RR_Logo_Icon` fra april 2026, samt eldre logoer fra 2025/januar 2026. Søk etter `Hovedlogo` gav ingen treff. Dette beviser ikke at pakken manglet i andre servermapper. Deploy-kjøringen gjør derfor en separat skrivebeskyttet filkartlegging i uploads og themes, med filnavn og SHA-256 i GitHub-loggen.

## Valg per flate

| Flate | Fil fra pakken | Begrunnelse |
|---|---|---|
| Desktop-header, radiofelt, footer | SVG/03-Hovedlogo-transparent-hvit.svg | Master med hvit RADIO/RUBBEN, rød play og bølger, verdilinje, på mørk flate |
| Mobilheader ≤720 px, liten kampkreditering | SVG/07-Uten-verdilinje-hvit.svg | Unngår uleselig liten verdilinje; verdiene finnes fortsatt som tekst i footer |
| Lys bakgrunn | SVG/04-Hovedlogo-transparent-svart.svg | Klar for lyse flater; aktive hovedflater er mørke |
| Favicon / Apple / app | Ikoner/favicon.ico, ikon-32/180/192/512.png | Originale størrelsestilpassede ikoner, ingen beskjæring |
| Forsidens Facebook/X-deling | PNG/01-Hovedlogo-mork.png | Opaque mørk master, 2400×1073; korrekte bildemål |
| Søkemotorenes organisasjonslogo / SoMe-profil | PNG/12-Profilbilde-mork.png | Kvadratisk med luft for rund beskjæring |
| Vannmerke | SVG/16-Ikon-hvit-transparent.svg | Klargjort for mørke foto/video; 17-varianten for lyse flater |

Alle lyse/mørke, liggende, profil- og monogramvarianter, PDF-oversikt, farger og veiledning følger med. Ingen nye vannmerker legges automatisk over eksisterende redaksjonelle bilder.

## Endringer og deploy

`inc/brand.php` samler variantvalg, ikoner, manifest og avgrensede Rank Math-filtre. `functions.php` laster denne fremfor tidligere custom-logo-/fallback-funksjoner. `header.php` velger mobilvariant med picture/source. `footer.php` får eksplisitte proporsjoner. Kampkreditering i `inc/bremnes-poll-test.php` bruker kompakt variant. `assets/css/brand.css` bevarer høyde/breddeforhold og tilpasser størrelse. `site.webmanifest` beskriver app-ikonene; det innebærer ikke offline-støtte eller en egen app.

Logovalget styres nå av versjonskontroll i det aktive temaet. Gamle WordPress custom_logo/site_icon-innstillinger og mediefiler beholdes for tilbakeføring, men overstyres i visningen. Artikkelbilder overstyres ikke generelt; bare forsiden og positivt identifiserte gamle merkevarebilder erstattes i delingsmetadata.

Repository: `toystad461/radiorubben-web`. GitHub-workflow `wordpress.yml` validerer PHP/shell, og publiserer med SSH/rsync til One.com `/run/webroots/r1417157/wp-content`. Apply krever main, sjekker aktivt tema og domene, tar privat backup og har rollback. Automatisk apply var avslått; eksplisitt apply kreves. Ingen filer lastes manuelt opp til produksjon. `theme-next/` er en separat stagingkandidat og `dist/` en statisk eldre variant; ingen av disse er aktivt produksjonstema eller del av WordPress-deployen.

## Verifisering

Alle 44 pakkefiler og original ZIP er verifisert byte for byte. PHP-syntaks kontrollert for hele aktive temaet. Delingsfiltrene er kontrollert for å bevare redaksjonelle bilder og erstatte identifiserte gamle merkevarebilder. Ikonstørrelser kontrollert. Lokal HTML-forhåndsvisning viser riktig master på desktop og kompakt variant ved 320 px; ingen horisontal overflyt ved 320 px. Endelig produksjonskontroll og GitHub-kjøringer registreres etter deploy.

## Manuell etterkontroll / avgrensninger

- Eksterne Facebook/Instagram/TikTok-profiler og radiokataloger må få riktig profilbilde via de respektive kontoene. Pakken er tilgjengelig, men disse kontoene er ikke endret.
- Eksisterende illustrasjoner med innbrent logo og eldre publiserte innlegg beholdes. Eventuell grafisk omarbeiding bør gjøres fra originalene.
- Nettlesere og sosiale plattformer kan beholde tidligere favicon/delingsbilde i mellomlager. Kontroller en fersk deling og hjemskjermsnarvei på fysisk iOS/Android.
- Ved fremtidig overgang til Next/child theme må samme merkevarekobling overføres før aktivering.

## Serverfunn og avgrenset publisering

GitHub-prøvekjøring [37348096868](https://github.com/toystad461/radiorubben-web/actions/runs/37348096868) bekreftet 15 PNG-originaler fra pakken i `uploads/2026/10/`, med identiske SHA-256: 01–04 og 07–17. 05/06 ble ikke inkludert i det første filfilteret og er derfor ikke avklart av denne kartleggingen. Ingen av de nye SVG-filene, ikonstørrelsene eller original ZIP ble påvist under de undersøkte navnene. Offentlig mediesøk var dermed ufullstendig. De nye filene var tilgjengelige på server, men ikke koblet til den aktive visningen.

Prøvekjøringen viste også eksisterende forskjeller mellom GitHub og produksjon i blant annet front-page.php, single.php og flere kampmoduler. Full `apply` ble derfor **ikke kjørt**. Den avgrensede `brand-dry-run`/`brand-apply`-flyten bruker den versjonsstyrte `scripts/brand-logo.patch` på kopier av de fire berørte produksjonsfilene, krever treff uten fuzz, PHP-validerer resultatene og sjekker at originalene ikke har endret seg før publisering. Bare disse fire filene og de nye merkevarefilene publiseres. GitHub-kjøringen registrerer før-/etter-hasher; serverens private backup lagrer originalfiler, patch, Git-commit og kvittering. Øvrige serverendringer bevares. Eksisterende avvik mellom server og repository krever en separat kodeavstemming før fremtidig full deploy.

Utvidet kartlegging [37348695480](https://github.com/toystad461/radiorubben-web/actions/runs/37348695480) bekreftet deretter **alle 17 PNG-originalene** fra pakken byte for byte i `uploads/2026/10/`. Første brand-dry-run stoppet trygt på avvikende kontekst rundt kampkrediteringen, før noen offentlig fil ble endret. Kampmodulen behandles derfor med en eksakt erstatning av ett logofunksjonskall (antall må være nøyaktig 1); de tre øvrige filene bruker fortsatt patch uten fuzz. Denne transformasjonen er versjonsstyrt i deploy-scriptet og inngår i hash-kvitteringen.
