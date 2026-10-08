# Quiztopplister – 8. oktober 2026

Brukeren ba om både toppliste per uke og samlet toppliste. Quizsiden viste tidligere bare inneværende ukes topp ti.

Ukens eksisterende toppliste beholdes. En ny visning under den viser samlet topp ti og et sammenleggbart ukearkiv med ukevalg. Arkivet bruker bare offentlig delte, ikke skjulte resultater fra eksisterende brukere. Resultater fra gamle quizrevisjoner, fremtidige/ugyldige uker og slettede kontoer utelates.

Samlet liste summerer poeng på tvers av uker; samlet tid avgjør ved lik poengsum. Beregningen bruker alle gyldige resultater, også dem som ikke var på ukens topp ti. Identiske fornavn slås ikke sammen; grupperingen bruker konto internt uten å utlevere bruker-ID.

Eksisterende delingsvalg gjaldt bare ukens liste. Ingen blir automatisk lagt i den nye samlede listen. Et separat noncebeskyttet valg lar innloggede medlemmer inkludere eksisterende og fremtidige offentlig delte resultater, eller trekke deltakelsen tilbake. Private og skjulte resultater tas aldri med.

Regresjonstesten dekker dato/revisjon, rangering, totaltelling, private/skjulte/slettede kontoer, samtykke, tilbakekalling og cache. Lokal nettleserkontroll med eksempeldata verifiserte ukevalg og frivillig deltakelse. Mobil390 px: dokumentbredde375 px, tabell440 px i egen305 px rulleflate.

Fire runtimefiler: inc/weekly-quiz.php, ny inc/weekly-quiz-history.php, assets/js/weekly-quiz.js og assets/css/weekly-quiz.css. Ingen eksisterende resultater eller delingsvalg migreres. Selektiv GitHub/SSH-utgivelse krever grønne kildekontroller, fersk baseline, byteverifisert backup og testet tilbakeføring. Publiseringsbevis føres her når det foreligger.

## Publisert og kontrollert

Publisert 8. oktober2026 kl.14:40:48 norsk tid. Kildecommit `f28bb43516ba012bb0ed539f9cbf752191613a31`; alle fem kildekontroller bestod. Leveringscommit `2857c0a`. Vellykket utgivelsesjobb: https://github.com/toystad461/radiorubben-web/actions/runs/37778543370. Test av firefilspublisering og tvungen tilbakeføring bestod før tilkobling. Fersk baseline samsvarte; øvrige pluginfiler var uendret.

Byteverifisert backup: `/home/cptk37ymg_w1417156/.radiorubben-deploy/backups/quiz-history-37778543370-1`. Tre eksisterende filer er sikkerhetskopiert; history-modulen er registrert som tidligere fraværende. Cachegenerasjonen og sidecachen er oppdatert. Den virkelige offentlige arkivtjenesten ble kontrollert i WordPress uten å publisere eller endre noen spillers resultat.

Fersk nettleserkontroll på quizsiden viste ukens liste, frivillig samlet liste og tre tidligere uker:14.,21. og28.september. Ukevalg ble kontrollert for14. og21.september. Ingen har valgt deltakelse i samlet liste ved kontrolltidspunktet, så den er tom. Vi trykket ikke samtykkeknappen på produksjon. Mobil390 px hadde dokumentbredde375 px og alle tre arkivalternativene.

Publisert skjermbilde: `quiz-topplister-published-20261008.jpg` i lokalt OneDrive-arbeidsområde. Ingen brukerdatabase eller testkonto er opprettet eller kopiert til Git.

| Pluginfil | Rå SHA256 etter publisering |
|---|---|
| inc/weekly-quiz-history.php | 73ea7150bbdcab56452663bb83c982ba3b03f1863a26e99f83e1ad3501762b47 |
| assets/css/weekly-quiz.css | fd4c2a8e8a1aa786484da294289b28d7ca055ef7bca59d49bf63a24a636da4bc |
| assets/js/weekly-quiz.js | 2fca8ee3fc095c38417152c2c59d47658b14f9cae3712edf46fa1a81ebd23d7c |
| inc/weekly-quiz.php | 63ba22a6df0cd4b394988092cdaf0180e1247639d63dfac103eb913c8335c4fa |

Ingen main-merge. Full Vipps-godkjenning av innloggingsrettelsen krever fortsatt at brukeren logger inn på nytt; serverhookene er kontrollert og rettelsen er publisert separat i PR70.
