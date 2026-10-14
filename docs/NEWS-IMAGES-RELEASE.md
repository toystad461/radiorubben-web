# Nyhetsbilder v1.2 – godkjent utrulling 14.10.2026

Thomas har godkjent OneDrive-opplasting, merge og utrulling. Det er ikke en
redaksjonell godkjenning av nye artikler.

## Bekreftet før WordPress-import

- Originalpakken er lastet opp til OneDrive, lest tilbake og kontrollert med
  SHA-256 `a2d27533d3b9455a96d7c967ed4f87f6aba3827156914a468104bb8b21303adc`.
- [Faktisk OneDrive-fil](https://radiorubben-my.sharepoint.com/personal/thomas_sellevold-oystad_radiorubben_no/Documents/Radio%20Rubben/02%20Profil%20og%20maler/Nyhetsbilder/RadioRubben-Nyhetsbilder-v1.2/RadioRubben-Nyhetsbilder-v1.2.zip),
  item `012VNUSIGB4FUDOXKHQRAYD3WJSRTTNAJY`, størrelse 39 646 211 byte.
- Studio PR92 er merget i `2c9a22154cf4d7ad5e3b65824256daf5f63b814b`.
  Jobb `38617872866` har publisert 21 endrede kodefiler, kontrollert etter-hasher
  og HTTPS/release-manifest. Privat backup:
  `/home/cptk37ymg_w1417156/.radiorubben-studio-deploy/backups/selective-38617872866-1`.
  Uendrede produksjonsavvik er bevart. Bildevalget er ennå av.
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

## Verifikasjonsgrense

Kode og lokale isolerte tester er ferdige. Denne loggen er skrevet før
WordPress-jobben: import, faktisk visning og modellpilot må bekreftes i en
etterfølgende driftskvittering før løsningen beskrives som aktiv.
