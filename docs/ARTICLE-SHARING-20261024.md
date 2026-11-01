# Artikkeldeling og felles artikkelmal – gjeldende kontroll 01.11.2026

Arbeidsområder: IT & Utstyr / Sosiale Medier / System & Kvalitet.
Hovedspor: PR #107, avstemt mot allerede merget PR #110.

## Bekreftet i produksjon

Selektiv utrulling og etterkontroll fullført **01.11.2026 kl. 16.34 norsk tid (15.34 UTC)**.
[Utrullingskvittering](https://github.com/toystad461/radiorubben-web/actions/runs/41321891298).

- Delingen fra #107 var allerede installert. Den er bevart uendret.
- Kun `wp-content/themes/radio-rubben-next/template-parts/content/article.php` ble installert fra #110.
- Begge publiserte artikler, 1594 og 1601, viser én felles KI-merknad rett etter ingressen.
- Hovedbilder, kilder, bildekrediteringer og eksisterende delingsparametere er kontrollert.
- WP-Optimize-sidecache og objektcache er tømt med pluginens eksisterende API.
- Ferske og ordinære offentlige adresser ga samme funksjonelle resultat, HTTP 200 og Age: 0.
- Alle 129 øvrige temafiler, inkludert article-share.php, var uendret.
- Privat før/etter-kontroll av poster og samtlige metadata for 1594, 1601, 1576 og 1603 besto.
  Ingen endring i lagret tekst, redaksjonell godkjenning, tilknyttede medier eller disse medienes metadata.
- WPVibe-utkastet er ikke publisert eller endret. Ingen ny artikkel, melding eller sosial publisering.
- PR #107 er avstemt med main og testet, men ikke merget. #110 var allerede merget.

## Forklaring på avviket 01.11

Forrige kontroll brukte WPVibes standard temalesing mens et utkast eksisterte.
Dette returnerte **utkastets** filer, ikke aktive produksjonsfiler.
Det ga et omvendt bilde av statusen:

| Komponent | Aktivt tema før denne utrullingen | WPVibe-utkast |
| --- | --- | --- |
| article-share.php | #107, ny Web Share-flyt | Eldre direkte Facebook-deling |
| content/article.php | Eldre mal | #110 med felles KI-merknad |

Direkte lesing med `scope=wp-content` og eksplisitt `themes/radio-rubben-next/...`
samsvarte med serverens SHA-256-kontroll via SSH. Offentlig nettleser og cookie-frie
serverkall bekreftet ny deling og gammel artikkelmal før utrulling.
Cache forklarte derfor ikke hovedavviket.

WPVibes HTML-verktøy viste dessuten utkastets kombinasjon selv ved angitt artikkeladresse.
Det ble ikke brukt som bevis for offentlig drift. Fremtidige kontroller må skille
utkast, aktiv kildefil, cache og ordinær offentlig nettleservisning.

## Kode, hash og tilbakeføring

Integrert testet kilde: `92a98a697bdfab0a1584b644c4c10e5f3fe267b8` i eksisterende PR #107.
Denne bevarer artikkelmalen byte for byte fra #110/main
`a1f41bd7a211604aaa6e7c271205bce5a50a4dff`.
Releasecommit: `5dc5e49cd298c2a17867b942419d2d99f37f6d65`,
eksisterende gren `release/article-sharing-20261024`.

| Fil | SHA-256 |
| --- | --- |
| article.php før | d3e9a51519b767f76275cd41cbcb30868563ac2835cfede6b946d2755ccd8c85 |
| article.php etter | a2fce7798bdc57bb8e895b3cb538d8f5df24fbe4e675ee0fbb50cd07b84212c8 |
| article-share.php uendret | 3452ca0eb3a9cccf2eb703514259e73dc4ad1be613e0e384585011abf9bbd966 |

Privat backup utenfor webrot:
`~/.radiorubben-deploy/backups/article-share-template-20261101T153438Z/article.php`.
Backupens hash og prøve på tilbakeføring i privat staging er kontrollert.
Skriptet stopper ved ukjent serverkode, bruker deploylås, atomisk filbytte og
automatisk tilbakeføring ved feil etter installasjon. Det tilbakefører aldri databasen
og nekter å overskrive en fil som noen andre har endret etter utrulling.

Ved manuell rollback: kontroller at dagens article.php fortsatt har forventet etter-hash,
gjenopprett bare denne filen atomisk fra backup, tøm samme cache og kontroller begge artikler.
Bevar article-share.php og all annen kode/data.

Første forsøk [41321806242](https://github.com/toystad461/radiorubben-web/actions/runs/41321806242)
stoppet før installasjon fordi webhotellet mangler realpath. Bash-basert stioppløsning rettet dette;
andre forsøk besto. Ingen aktiv kode ble endret i første forsøk.

## Tester

[CI på integrert kilde](https://github.com/toystad461/radiorubben-web/actions/runs/41321512856):
fem grønne kontroller totalt; Next-jobben kontrollerte 138 PHP-filer,
JS/JSON/presentasjonsgrenser, 20 delingstester, sju integrasjonsscenarier for artikkelmalen,
eksisterende Vipps-/avstemningsprøver, 15 lesekontrolltester og pakking.
Releasejobben lintet også de tre avgrensede leveranseskriptene.

Integrasjonsscenariene dekker gammel toppmerking, Gutenberg-ingress og kjent bunntekst,
sitat/bilde først, ukjent redaksjonell bunntekst, passordbeskyttet innlegg og vanlig side.
Innhold, kildehenvisninger, bildekreditering og paginering bevares.

| Kontroll | 1594 | 1601 |
| --- | --- | --- |
| Én KI-merknad etter ingress | Bestått | Bestått |
| Hovedbilde lastet og dekodet | 1086 × 502 | 1266 × 900 |
| Kildelenker og bildekreditering | Bevart | Bevart |
| Canonical, Facebook, WhatsApp, e-post, kopierverdi | Bestått | Bestått |
| Offentlig nettleser: ny delingskode initialisert | Bestått | Bestått |
| Klikk Kopier lenke | Viser «Lenken er kopiert!» | Viser «Lenken er kopiert!» |
| Horisontal overflyt i testet desktopvisning | Ingen | Ingen |

Facebook-klikk åpnet korrekt sharer.php med artikkel-URL, men testnettleseren krever Facebook-innlogging.
WhatsApp-klikk gikk til en app-protokoll testnettleseren ikke tillater.
E-postens emne/tekst/URL er kontrollert, men installert e-postapp er ikke testet.
Eksakt kopiert tekst er bekreftet i isolert API-test; testnettleserens separate utklippstavlelesing
returnerte tomt, så den fysiske utklippstavlen er ikke sluttverifisert.
Native deling på fysisk iPhone og appenes forhåndsvisning er fortsatt uprøvd.

#110 legger KI-merknaden til alle vanlige, ubeskyttede innlegg. Denne leveransen
endrer ikke det tidligere vedtatte omfanget eller innfører et nytt artikkelbasert KI-skille.

## iPhone – gjenstående brukertest

Åpne disse i Safari, og last siden på nytt:
- [1594 – ferge](https://www.radiorubben.no/b-ruten-arsvagen-mortavika-innstilt-30-oktober-2026/)
- [1601 – volleyball](https://www.radiorubben.no/bomlo-ungdom-gratis-volleyballkveld/)

1. Trykk Facebook eller «Del artikkelen», velg Facebook og se at riktig artikkellenke følger med.
   Avbryt før publisering.
2. Trykk WhatsApp og se at riktig tittel/lenke følger med. Avbryt før sending.
3. Trykk E-post og kontroller emne og lenke. Avbryt.
4. Trykk Kopier lenke, lim inn i Notater, og kontroller at lenken er identisk med artikkeladressen.
5. Kontroller hovedbildet, én KI-boks etter ingressen og lesbar layout uten sideveis scrolling.

## Historisk leveranse

#107s opprinnelige delingskilde `f2fb30db3c25c854fd68236540723e56fa88bac3`
ble installert 24.10.2026. [Kvittering](https://github.com/toystad461/radiorubben-web/actions/runs/40072280818).
Den leveransen er ikke tilbakeført; dens filhash ble bekreftet på nytt 01.11.
