# Retur etter Vipps-innlogging – 8. oktober 2026

Fersk lesing av produksjonens `plugins/rr-site-functions/inc/quiz-controls.php` viste at returregelen bare gjenkjente `/quiz/`. Vipps-innlogging fra `/min-side/` ble dermed overlatt til Vipps sitt standardmål, som brukeren rapporterte var profilsiden.

Regelen tillater nå to konkrete offentlige ruter på nettstedets eget vertsnavn. Innlogging fra `/min-side/` går tilbake dit. `/quiz/` beholder `#rr-weekly`. Andre ruter, eksterne vertsnavn og mislykket innlogging overstyres ikke. Ingen profil-, konto- eller quizdata endres. Begge eksisterende Vipps-hooks, inkludert bekreftelse ved ny konto, bruker samme returregel.

17 isolerte regresjonstilfeller dekker array og Vipps sin ArrayAccess-økt, begge ruter, forhåndsvisning, feil sesjonsdata, eksterne vertsnavn og urelatert innlogging. Faktisk mobilgodkjenning i Vipps krever at brukeren gjennomfører innloggingen; ingen autentisering etterlignes.

Endringen er avgrenset til én eksisterende pluginfil. `SOURCE-MANIFEST.json` er historisk migreringsbevis og omskrives ikke; review-kildetreet er oppdatert. Utgivelsesstatus og ferske serverbevis føres nedenfor når de foreligger.

## Publisert

Publisert 8. oktober2026 kl.14:30:21 norsk tid. Kilde `47de32dc6c329d1a50a4b423ee4626fba6c6e8c2`; fem kildekontroller bestod. Utgivelsesjobb: https://github.com/toystad461/radiorubben-web/actions/runs/37777272383. Offline tvungen tilbakeføring bestod. Serverens virkelige retur- og bekreftelseshooks ble kontrollert for begge ruter med ArrayAccess-økt, uten faktisk autentisering.

Rå SHA256 før: `0bb7ff2734f0cd2e6fa87112be2e4e7765b7e562015251ae99b25c6a396644cd`. Etter: `98e3d37b8ddf8b1eaf2c18798a7d3e91a4085e0b327732d3e7de7168d806e36d`. Byteverifisert backup: `/home/cptk37ymg_w1417156/.radiorubben-deploy/backups/member-login-return-37777272383-1`. Øvrige pluginfiler kontrollert uendret. Full Vipps-godkjenning gjenstår hos brukeren.
