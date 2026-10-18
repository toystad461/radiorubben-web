# Versjonerte språkregler og publiseringskontroll

Semantisk PHP-port av `toystad461/RadioRubben-robot` PR #3, commit
`5b599db7efb86204798b0487db3f291175438f5e`. Språkstandarden er uendret, versjon
`1.0.0`, i `includes/editorial-quality.php`. Endring av språkstandarden krever
ny `RULES_VERSION` og oppdaterte tester.

## Avgrensning

Denne endringen bygger direkte på `radiorubben-web` PR #29, commit
`5a7aec0d7f08d0ffd8f4e7c864395cca76054c12`, i en egen avhengig gren.
Den endrer ikke #29, Robot-repoet, spilleradapteren, Fotballdata-transporten,
e-postkonfigurasjon eller e-posttransport. Manuell sluttgodkjenning beholdes.
PR #30/#31 og nyere produksjonskode er ikke flettet inn.
**Ingen deploy, aktivering, publisering eller produksjonskall er utført.**

## Flyt

1. Genereringsinstruksen får den faste språkregelen, også for spillerforslag.
2. En separat faktaredaktør kontrollerer hele teksten mot faktapakken: lag,
   hjemme/borte, dato, resultat, navn, tidsrekkefølge og kildekrevende påstander.
   Artikkelens egne `checks` og læringseksempler er ikke faktakilder.
3. Språkvask retter tegnsetting, uklare bytter, gjentakelser og unaturlig språk.
4. Endret tittel, ingress eller avsnitt får **ny separat faktakontroll**. En
   godkjenning før omskriving kan aldri godkjenne den omskrevne teksten alene.
5. Mekaniske kontroller krever begge lag, dato og sluttresultat i hjemmelag–bortelag-
   rekkefølge, bevarer verifiserte navn som allerede er brukt, og kontrollerer
   komma og dokumenterte bytter («spiller inn kom inn for spiller ut»).
6. Teksten lagres som utkast, også ved kontrollfeil. Ingen godkjenning gis ved
   timeout, ugyldig svar, uferdig kamp, avvik eller feil i språkvasken.
7. `wp_insert_post_data` sperrer publisering, planlegging og privat publisering
   uten gyldig kvalitetskontroll. WP-Cron kontrollerer planlagte innlegg på nytt
   før WordPress sin publiseringshandler. Ordinære innlegg berøres ikke.

Spillerforslag bruker fortsatt sin eksisterende ja/nei/endre-flyt. Et direkte
publiseringsforsøk fra WordPress kan ikke hoppe over denne sluttgodkjenningen.
Testforslag forblir upubliserbare. Kampreferater bruker fortsatt WordPress sin
manuelle publiseringshandling. En godkjent kvalitetskontroll publiserer ingenting.

## Sporbarhet og redigering

`_rrfr_quality_review` lagrer `rulesVersion`, `factsHash`, `postHash`,
`checkedAt`, `languageStatus`, faktakontrollene, funn og `publishable`.
Kampens `_rrfr_ai_review` og spillerforslagets beslutningshistorikk inneholder
også regelversjonen. Hashen dekker nøyaktig lagret tittel, innhold og utdrag.
Endret tekst, nye kildefakta, manuelle bytter eller ny regelversjon gjør gammel
kontroll ugyldig. Oppslag mot siste **lagrede** faktagrunnlag gjør ingen nye
API-kall ved publisering; kildeinnhenting skjer fortsatt i eksisterende flyt.

I WordPress-redigeringen viser «Fotballrobotens kvalitetskontroll» versjon og
funn. Lagre teksten som utkast og velg «Kontroller lagret tekst» etter endringer.
Kontrollen krever innlogging, REST-nonce, redigeringsrettighet og uendret
lagringshash. Den overskriver ikke manuell tekst. Hvis språkvask foreslår
endringer, må redaktøren rette/lagre dem og kontrollere på nytt. Last siden på
nytt for å se hele forslaget. Deretter kreves vanlig manuell sluttgodkjenning.
Gamle robotutkast uten kontroll må også gjennom dette steget. Regelbasert
faktatekst kan fortsatt opprettes uten AI, men publisering krever kontroll.

## Verifisering og begrensninger

Isolerte tester dekker kildefeil, komma, inn/ut-retning, navne-/dato-/lag-/
resultatavvik, faktakontroll etter omskriving, feil og ugyldige svar fra
språkvask, gammel godkjenning etter redigering, foreldet regelversjon,
samtidig redigering, planlagt publisering og manuell spillergodkjenning.
Eksisterende tester for parser, spillerdata, Fotballdata, e-post, læring,
rapport, skriver og oppstart kjøres også. Alle eksterne kall er mocket.

Kjør `php tests/editorial-quality.php`, `php tests/publication-gate.php` og
de øvrige `tests/*.php` fra pluginmappen. Den nye PR-workflowen kjører på
både `main` og `feature/fotballrobot-spillere`, har kun leserettighet og
inneholder ingen deployjobb. Eksisterende deploy-workflow er uendret.

Fritekstkontrollen kan ikke bevise alle påstander. Mekaniske kontroller finner
kjente feil; øvrige navn/påstander og motstrid vurderes av separat AI-kontroll
og redaktøren. Spillerprofiler behandles ikke som ferdigspilte kamper.
Generering bruker tre AI-kall, fire dersom språkvasken endrer teksten. Faktisk
modellrespons, tidsbruk/servergrenser, WordPress-editorens visning og en ende-
til-ende prøve på staging gjenstår før utrulling. Publiseringsvernet gjelder
WordPress' ordinære lagrings- og planleggingsflyt, ikke direkte databaseskriving
eller tredjeparts kode som omgår WordPress sine lagringshooks.

Produksjon har tidligere vært dokumentert som plugin 0.9.0, mens denne
kildegrenen bygger på 0.6.0. Sammenlign med fersk produksjonskode før eventuell
senere utrulling; hele denne avhengige grenen skal ikke deployes direkte.
