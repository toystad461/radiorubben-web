# Artikkelforslag og godkjenning – 0.6.0

Fotballrobot → Artikler til godkjenning. Administrator kan lage et tydelig merket testforslag fra en lagret spillerprofil. Testinnlegg forblir utkast også ved godkjenning og kan ikke publiseres eller planlegges fra den vanlige editoren.

Forslag skrives med eksisterende AI-oppsett og kontrolleres i et separat faktaredaktørkall. E-post går til thomas.sellevold-oystad@radiorubben.no med From Fotballroboten og avsender fra privat serverkonfigurasjon. Ingen standardavsender brukes. WordPress bekrefter kun overlevering til e-postsystemet, ikke mottak. Serverens e-postoppsett må tillate avsenderdomenet.

E-postlenken åpner en innlogget godkjenningsside; den utfører ingen handling. Administrator velger ja, nei eller ber om endringer med kommentar. Kommentarer gir en ny AI-versjon som krever ny godkjenning. Svar direkte på e-posten behandles ikke. Ja publiserer bare ordinære forslag, aldri testforslag. Nei beholder utkastet og registrerer avslaget.

Automatisk oppretting er av som standard og aktiveres på samme side. Bare nye hendelser etter aktivering behandles, maksimalt ett forslag per spillerkjøring. Hendelser fra samme spilleroppdatering samles. Allerede reserverte forslag gjenbrukes; skrivefeil og avbrudd gir ingen automatisk betalt gjentakelse. Feilet skriving og e-post kan prøves på nytt eksplisitt i administrasjonen. Etter prosessavbrudd krever vedvarende lås manuell kontroll før fjerning.

Privat innleggsmetadata lagrer faktakopi, tekstversjon, hash, e-poststatus og beslutningshistorikk. Rettigheter, nonce, versjon og innholdshash kontrolleres ved handlinger. Endringer under AI-skriving overskrives ikke. Ingen anonyme godkjenningslenker eller innkommende e-postintegrasjon.

Verifisering: `php tests/player-review.php` dekker 56 kontroller med simulert AI/e-post, inkludert duplikater, replay, rettigheter, testpubliseringssperre, faktiske publiseringsvalg, kommentarer, feilhåndtering og låsing. Kjør også eksisterende spiller-, lærings- og skrivetester. Reell levering og brukerens ja/nei/kommentar må kontrolleres i praktisk prøve.

## Praktisk prøve 26. september 2026

0.6.0 installert i WordPress. Tiril (profil 1011, FIKS 3942773) ga testutkast 1016, «[TEST] Sellevold-Øystad med ett mål i Branns sesongstatistikk». Reell AI-skriving og faktakontroll fullførte. Utkastet venter på godkjenning og er sperret for publisering. WordPress rapporterte at e-postsendingen feilet; mottak er ikke bekreftet. Automatiske forslag er fortsatt av mens flyten testes.


## Privat avsender og transportstatus – 28. september 2026

Varsling er deaktivert til en gyldig avsender **og eksplisitt operatørgodkjenning** er konfigurert. Utkast og godkjenning fungerer fortsatt. Bruk følgende konstanter i privat `wp-config.php` (før WordPress lastes), eller miljøvariabler med samme navn:

| Innstilling | Verdi |
| --- | --- |
| `RRFR_REVIEW_FROM_EMAIL` | Én e-postadresse som driftsansvarlig har bekreftet finnes og er tillatt/autentisert for valgt transport. Ingen standardverdi. |
| `RRFR_REVIEW_FROM_APPROVED` | PHP-verdien `true`, eller miljøverdien `1`, først etter denne bekreftelsen. Manglende eller annen verdi deaktiverer varsling. |

Definerte konstanter har forrang over miljøvariabler, også når de deaktiverer sending. Adressen må være gyldig etter WordPress `is_email()` og uten kontrolltegn; visningsnavn og ekstra mottakere aksepteres ikke. Godkjenningsflagget er operatørens bekreftelse, ingen automatisk kontroll av konto eller domenets e-postoppsett. Ikke lagre privat konfigurasjon, SMTP-passord eller andre hemmeligheter i repoet. Denne endringen oppretter ingen konto og setter ikke opp SMTP.

| Lagret e-poststatus | Administrators visning |
| --- | --- |
| `not_configured` | Ikke konfigurert; sending deaktivert, ingen transportkall gjort. Knappen for ny sending er deaktivert så lenge konfigurasjonen mangler. |
| `rejected` | Transport avvist; `wp_mail()` returnerte ikke `true` eller kastet et unntak. Ingen bevist årsak oppgis. |
| `accepted` | Transport akseptert; `wp_mail()` returnerte `true`. Levering til innboksen er ikke bekreftet. |
| `failed` (eldre data) | Tidligere sending feilet; årsaken er ikke fastslått. Historiske feil omklassifiseres ikke. |

Etter privat konfigurering kan administrator eksplisitt prøve e-post på nytt fra et ventende forslag. Konfigurasjonsendringen alene sender ikke eksisterende forslag på nytt. En akseptert eller uavklart pågående sending gjentas ikke automatisk.

Studio PR #11 dokumenterer at Uniweb-panelet 28.09 ikke viste eksisterende e-postkonto på domenet. Dette **kan** forklare feilen ved prøveutkast 1016 den 26.09, men er ikke bevist årsak. Reell transport og innbokslevering er fortsatt uprøvd i denne refaktoreringen.

Verifisert lokalt med PHP 8.2 (WASM): 56 mockede kontroller, inkludert de tre statusene, faktisk administrasjonsvisning, null transportkall ved manglende/ugyldig/ikke godkjent avsender, header-injeksjon, transportunntak, eksplisitt retry og eksisterende publiseringssperre. Testen bruker ingen virkelig e-post, AI, WordPress-database eller publisering. Den kjøres også av WordPress-kontrolljobben i PR-en. Ingen deploy, e-postkonto eller endring av testutkast 1016 inngår.
