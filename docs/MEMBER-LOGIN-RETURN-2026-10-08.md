# Retur etter Vipps-innlogging – 8. oktober 2026

Fersk lesing av produksjonens `plugins/rr-site-functions/inc/quiz-controls.php` viste at returregelen bare gjenkjente `/quiz/`. Vipps-innlogging fra `/min-side/` ble dermed overlatt til Vipps sitt standardmål, som brukeren rapporterte var profilsiden.

Regelen tillater nå to konkrete offentlige ruter på nettstedets eget vertsnavn. Innlogging fra `/min-side/` går tilbake dit. `/quiz/` beholder `#rr-weekly`. Andre ruter, eksterne vertsnavn og mislykket innlogging overstyres ikke. Ingen profil-, konto- eller quizdata endres. Begge eksisterende Vipps-hooks, inkludert bekreftelse ved ny konto, bruker samme returregel.

17 isolerte regresjonstilfeller dekker array og Vipps sin ArrayAccess-økt, begge ruter, forhåndsvisning, feil sesjonsdata, eksterne vertsnavn og urelatert innlogging. Faktisk mobilgodkjenning i Vipps krever at brukeren gjennomfører innloggingen; ingen autentisering etterlignes.

Endringen er avgrenset til én eksisterende pluginfil. `SOURCE-MANIFEST.json` er historisk migreringsbevis og omskrives ikke; review-kildetreet er oppdatert. Utgivelsesstatus og ferske serverbevis føres nedenfor når de foreligger.
