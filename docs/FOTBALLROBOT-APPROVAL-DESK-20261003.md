# Enklere sluttgodkjenning

Samme innloggede godkjenningsside samler spillerforslag og kampomtaler. Eksisterende e-postlenker virker fortsatt.

- Oversikten prioriterer ordinære, ferdigkontrollerte utkast. Andre statuser og tester ligger separat.
- Leseren ser lagret tittel, valgt bilde med bildetekst, artikkel og kilder før hovedknappen «Godkjenn og publiser».
- Endringsønsker, avvisning, innstillinger og historikk ligger bak tydelige utvidbare felt.
- Spillersaker kan sendes til eksisterende AI-omskriving med kommentar. Kampomtaler redigeres i WordPress; ingen ny betalt reserveflyt.
- Etter manuelle tekstendringer kan eksisterende kvalitetskontroll startes fra samme side. Den kjører bare etter et uttrykkelig klikk, skriver ikke om teksten og publiserer aldri.
- Publisering krever riktig rettighet, nonce, et uendret forslag og aktuell faktakontroll. Tester og papirkurv blir aldri publisert her. Dobbel innsending avvises.
- Kampomtaler får en intern beslutningshistorikk når redaktøren faktisk velger godkjenning eller avvisning. Innlasting av siden skriver ikke artikkeldata.

Godkjenningsside: https://www.radiorubben.no/wp-admin/admin.php?page=rrfr-player-review

Verifikasjon: egne mocktester for spiller- og kampgodkjenning, endret tekst/bilde/faktagrunnlag, rettigheter, gjenbruk av gammel side, avvisning/gjenåpning, testinnlegg og samtidig redigering. Mobil- og desktopkontroll bruker syntetiske HTML-fixtures; ingen ekte artikkel godkjennes som test.

Aktivering skal bare kopiere berørte runtime-filer etter tester og kontroll av forventede filhasher. Eksisterende artikler, metadata, spillerhistorikk, køer, varslinger og innstillinger skal ikke migreres eller nullstilles.

## Aktivert 3. oktober 2026 kl. 23.30 (Europe/Oslo)

Seks runtime-filer ble aktivert selektivt fra `77bf90af9c0006b2c9fef6b5694612073bf289ea`. Alle 15 PHP-testprogrammer var grønne, inkludert 40 kontroller av den nye siden. Mobil (390 px) og desktop (1280 px) ble kontrollert med rendret testinnhold, uten å sende et godkjenningsskjema.

Livekontrollen rendret ni eksisterende utkast i minnet. Før-/etterfingeravtrykk av robotartikler, metadata og spillerdata var identiske. Monitoren svarte fortsatt med fem aktive profiler, planlagt neste kontroll og `manual_approval_required`. Ingen artikkel ble godkjent eller publisert under utrullingen.

- Kodegjennomgang: https://github.com/toystad461/radiorubben-web/pull/44
- Aktivering: https://github.com/toystad461/radiorubben-web/actions/runs/37155321824
- Integrasjons- og visningstest: https://github.com/toystad461/radiorubben-web/actions/runs/37155324966
