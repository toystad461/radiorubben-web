# Nyhetsbilder v1.2 – godkjent utrulling 14.10.2026

Thomas har godkjent OneDrive-opplasting, merge og utrulling. Det er ikke en
redaksjonell godkjenning av nye artikler.

## Bekreftet innføring

- Originalpakken er lastet opp til OneDrive, lest tilbake og kontrollert med
  SHA-256 `a2d27533d3b9455a96d7c967ed4f87f6aba3827156914a468104bb8b21303adc`.
- [Faktisk OneDrive-fil](https://radiorubben-my.sharepoint.com/personal/thomas_sellevold-oystad_radiorubben_no/Documents/Radio%20Rubben/02%20Profil%20og%20maler/Nyhetsbilder/RadioRubben-Nyhetsbilder-v1.2/RadioRubben-Nyhetsbilder-v1.2.zip),
  item `012VNUSIGB4FUDOXKHQRAYD3WJSRTTNAJY`, størrelse 39 646 211 byte.
- Studio PR92 er merget i `2c9a22154cf4d7ad5e3b65824256daf5f63b814b`.
  Jobb `38617872866` har publisert 21 endrede kodefiler, kontrollert etter-hasher
  og HTTPS/release-manifest. Privat backup:
  `/home/cptk37ymg_w1417156/.radiorubben-studio-deploy/backups/selective-38617872866-1`.
  Uendrede produksjonsavvik er bevart. Automatvalg er senere aktivert etter bestått modellpilot, se nedenfor.
- WordPress er 7.1.3, PHP 8.2.34, Next-tema 2.0.0-rc.5 og Optimole 4.2.16.
  Ingen endring i fotballrobotens aktive 0.10.8 eller øvrige utvidelser.

## Avgrenset release

`news-images-release.yml` er manuell og krever eksakt main-SHA. `install` tar
en kortvarig nedlastingsreferanse fra den verifiserte OneDrive-filen. URL-en
maskeres i Actions; originalen kontrolleres mot fast SHA og størrelse før SSH.
Bare sju runtime-filer i den nye utvidelsen inngår, med transportmanifest.
Eksisterende utvidelsesfiler må være identiske; ukjente endringer overskrives ikke.
Ordinær bred WordPress-deploy hoppes over for en merge som bare berører denne
utvidelsen. Eksisterende SSH-nøkkel og vertskontroll brukes uendret.

Serveren tar privat førkvittering og bruker eksisterende deploylås. Hele pakken
valideres før import. Originalen arkiveres privat, og importen kjøres to ganger
med krav om identiske faktiske medie-ID-er/URL-er og nøyaktig 21 identiteter.
Publiserte/ikke-publiserte artikler, tekst og hovedbilder avstemmes før/etter.
Ingen prøveartikkel opprettes. En syntetisk publisering uten menneskelig
godkjenning kontrolleres direkte mot publiseringssperren uten databaseinnsetting.

`verify` kontrollerer installert kode, katalog, filer, aktiv utvidelse og
publiseringssperre. Begge gir privat JSON-kvittering og en syntetisk HTML-visning
fra faktisk WordPress-bildepipeline og aktivt temas CSS for mobil/skrivebord.

`activate-studio` bruker en separat PHP-prosess for å unngå dobbel kontraktlasting.
Den krever eksakt deploykvittering og uendrede kodehasher. Åtte syntetiske saker
kjøres gjennom faktisk konfigurert modell og autentisert produksjonskatalog:
Bremnes, Brann, politikk, tjenesteinformasjon, konsert, idrett, frisør og verksted.
Ingen sak lagres. Bare når alle består, opprettes den private filen
`studio-private/config/news-images-enabled.php` med `return true`.
Nøkler, øvrig konfigurasjon og sendelisten skal være byteidentiske før/etter.

## Tilbakeføring

Ved installasjonsfeil deaktiveres en utvidelse som nettopp ble aktivert.
Importerte medieidentiteter beholdes for sikker gjenopptakelse, aldri slettes
som automatisk feilretting. Ved senere behov kan bildevalget slås av i den
avgrensede flaggfilen; allerede lagrede valg og godkjenninger skal bevares.
Studio-kode har eksakt selektiv backup. Ikke gjenopprett redaksjonelle data
over senere arbeid. Ved ukjent drift stopper jobben uten overskriving.

## Verifisert WordPress-drift

Kontrolljobb `38621341817` (14.10.2026 kl. 19:10 UTC) bestod på main
`292612b76f041cb33d8a3216c4162c1213ee8a02`. Utvidelsen er aktiv. Katalog-API,
21 mediefiler med ID 1352–1372, originaldimensjoner og SHA-256 er kontrollert.
Alle 21 offentlige PNG-er er også lastet ned over HTTPS og sjekket byteidentiske.
Import ble gjennomført to ganger i jobb 38620442567 med samme faktiske mapping;
jobben stoppet senere på pluginlistens cache. Aktivering og etterkontroll er
fullført separat. De første CLI-forsøkene stoppet før import på canonical-path
kontroll; dette ble rettet uten å svekke stibegrensningen.

76 eksisterende artikler har uendret status, tekst, hovedbilde og bildevalg.
Samlet før/etter-hash: `50d63370982cea4c66253bd7896dfa5878b44a2d01aaa2af0171e5cd630b99f8`.
Privat kodebackup: `/home/cptk37ymg_w1417156/.radiorubben-deploy/backups/news-images-38621341817-1`.
Serverarkiv: `/customers/9/3/1/cptk37ymg/users/cptk37ymg_w1417156/.radiorubben-deploy/news-images/archive/RadioRubben-Nyhetsbilder-v1.2.zip`.
Medier: `/run/webroots/r1417157/wp-content/uploads/rr-news-images/RadioRubben-Nyhetsbilder-v1.2`.

Syntetisk forhåndsvisning fra produksjonens PHP-bildepipeline, aktive CSS
(inkludert faktisk WP-Optimize-frontsidebundle) og hashkontrollerte medier er
rendret og visuelt inspisert ved 390 og 1280 px: sju nyhetskort, artikkel og
prioritert kort. Alle ni bilder vises hele med KI-bildetekst, uten clipping eller
horisontal overflyt. Dette opprettet ingen artikkel. Skjermbilder og faktisk
medieregister er arkivert i samme OneDrive-mappe som originalpakken.

## Modellpilot før aktivering

Første pilot stoppet trygt på ugyldig JSON. Diagnostikk i 38622707537 bekreftet
Markdown-innpakning og omskrevne belegg. Studio-rettelsen bruker samme strenge
JSON-skjema som eksisterende kontrollfunksjoner og begrenser belegg til ordrette
utdrag; hele saken vurderes fortsatt. Ingen validator eller godkjenningsgrense
svekkes. Endelig pilot `38623968749` bestod 14.10.2026 kl. 19:29 UTC med faktisk
`gpt-4.1-mini` og Studio-release `96ff332d9b26f07b3be68c1d9cf2c9e58b6a7c60`.
Bremnes → bremnes-fotball, Brann → sport, politikk → politikk, tjeneste →
kommunesaker, konsert → kultur-arrangement, idrett → sport, frisør →
INGEN_PASSENDE_BILDE, maritimt verksted → naeringsliv. Alle åtte bestod og
var stabile ved retry. Katalogen returnerte samme 21 medieidentiteter før/etter.

Flagget er atomisk aktivert og lest tilbake. Privat konfigurasjon og sendeliste
var uendret. Aktiveringsbackup:
`/home/cptk37ymg_w1417156/.radiorubben-studio-deploy/backups/news-images-activation-38623968749-1`.
Studio PR93s selektive deploy `38623594055` bestod med privat kodebackup.
WordPress-koden er uendret etter den verifiserte importen. Original, faktisk
medieregister, skjermkontroll og DRIFTSKVITTERING.txt er lagret i OneDrive.
Dette dokumenterer aktiv drift for nye utkast; den vanlige menneskelige
sluttgodkjenningen kreves fortsatt på hver virkelig sak.
Ingen ekte sak er opprettet, godkjent eller publisert under kontrollen. Eksterne
sosiale plattformers egen beskjæring må kontrolleres ved faktisk deling.
