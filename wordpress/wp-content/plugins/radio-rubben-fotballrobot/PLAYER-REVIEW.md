# Artikkelforslag og godkjenning – 0.6.0

Fotballrobot → Artikler til godkjenning. Administrator kan lage et tydelig merket testforslag fra en lagret spillerprofil. Testinnlegg forblir utkast også ved godkjenning og kan ikke publiseres eller planlegges fra den vanlige editoren.

Forslag skrives med eksisterende AI-oppsett og kontrolleres i et separat faktaredaktørkall. E-post går til thomas.sellevold-oystad@radiorubben.no med From Fotballroboten <fotballrobot@radiorubben.no>. WordPress bekrefter kun overlevering til e-postsystemet, ikke mottak. Serverens e-postoppsett må tillate avsenderdomenet.

E-postlenken åpner en innlogget godkjenningsside; den utfører ingen handling. Administrator velger ja, nei eller ber om endringer med kommentar. Kommentarer gir en ny AI-versjon som krever ny godkjenning. Svar direkte på e-posten behandles ikke. Ja publiserer bare ordinære forslag, aldri testforslag. Nei beholder utkastet og registrerer avslaget.

Automatisk oppretting er av som standard og aktiveres på samme side. Bare nye hendelser etter aktivering behandles, maksimalt ett forslag per spillerkjøring. Hendelser fra samme spilleroppdatering samles. Allerede reserverte forslag gjenbrukes; skrivefeil og avbrudd gir ingen automatisk betalt gjentakelse. Feilet skriving og e-post kan prøves på nytt eksplisitt i administrasjonen. Etter prosessavbrudd krever vedvarende lås manuell kontroll før fjerning.

Privat innleggsmetadata lagrer faktakopi, tekstversjon, hash, e-poststatus og beslutningshistorikk. Rettigheter, nonce, versjon og innholdshash kontrolleres ved handlinger. Endringer under AI-skriving overskrives ikke. Ingen anonyme godkjenningslenker eller innkommende e-postintegrasjon.

Verifisering: `php tests/player-review.php` dekker 29 kontroller med simulert AI/e-post, inkludert duplikater, replay, rettigheter, testpubliseringssperre, faktiske publiseringsvalg, kommentarer, feilhåndtering og låsing. Kjør også eksisterende spiller-, lærings- og skrivetester. Reell levering og brukerens ja/nei/kommentar må kontrolleres i praktisk prøve.

## Praktisk prøve 26. september 2026

0.6.0 installert i WordPress. Tiril (profil 1011, FIKS 3942773) ga testutkast 1016, «[TEST] Sellevold-Øystad med ett mål i Branns sesongstatistikk». Reell AI-skriving og faktakontroll fullførte. Utkastet venter på godkjenning og er sperret for publisering. WordPress rapporterte at e-postsendingen feilet; mottak er ikke bekreftet. Automatiske forslag er fortsatt av mens flyten testes.
