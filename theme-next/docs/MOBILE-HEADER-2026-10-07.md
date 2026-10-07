# Fast mobilheader – 7. oktober 2026

## Endring
På skjermer opptil 720 px blir logo, Min Rubben og meny liggende øverst ved rulling.
Kampfeltet ruller ut av bildet. Over 720 px beholder hele toppen den eksisterende
sticky-visningen. Under 361 px vises Min Rubben med ikon; tilgjengelig navn beholdes.
Menyen åpner under topplinjen. Innholdsankre tar høyde for denne og WordPress-verktøylinjen.
Uten JavaScript beholdes synlig navigasjon. CSS-versjonene endres for cachefornyelse.

## Kilde og avgrensning
Basert på dokumentert produksjonsgren `release/next-production-20261006`,
commit `f7cce8c4129e68361e65e86928aca71c24140026` (Next rc.5 / PR #64).
Bare fire temafiler endres: `template-parts/site/header.php`, `inc/setup.php`,
`assets/css/mobile-shell.css` og `assets/css/components.css`.

## Kontroll
PHP 8.2-syntaks via PHP.wasm: begge endrede PHP-filer består.
Nettleserkontroll i lokal Chromium med anonym offentlig HTML og CSS hentet
7. oktober, oppdatert toppstruktur og lokale temaressurser; eksterne kall blokkert.
Ni scenarier bestått: 320, 390, 720, 844×390 og 1440 px; adminverktøylinje
ved 390 og 650 px; uten JavaScript; uten kampfelt.
Kontrollert fast topp ved rulling, kampfelt ute av bildet på mobil, beholdt
skrivebordsvisning, horisontal overflyt, åpning/lukking av meny med Escape,
meny innenfor skjermen og avspilleranker nedenfor toppen.
Dette er en lokal nettleserkontroll, ikke en fysisk iPhone-/Safari-test.

## Utrullingsstatus
Ikke publisert ved opprettelse av denne endringen. WordPress krever ny innlogging
i arbeidsøkten. Ingen produksjonsfiler eller innstillinger er endret.
Før publisering: les tilbake og sikkerhetskopier de fire aktive filene, sammenlign
med kildegrunnlaget, last opp bare rettelsen, og tøm WP-Optimize sin minifiseringscache.
Den offentlige siden bruker en kombinert minifisert CSS-fil; nye versjonsparametere
alene er ikke bevis for at denne filen er bygget på nytt.
Kontroller deretter offentlig HTML/CSS og mobilmeny. Tilbakeføring er å gjenopprette
de fire sikkerhetskopierte filene og bygge CSS-cache på nytt.
