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
