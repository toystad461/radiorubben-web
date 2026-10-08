# Quiztopplister – 8. oktober 2026

Brukeren ba om både toppliste per uke og samlet toppliste. Quizsiden viste tidligere bare inneværende ukes topp ti.

Ukens eksisterende toppliste beholdes. En ny visning under den viser samlet topp ti og et sammenleggbart ukearkiv med ukevalg. Arkivet bruker bare offentlig delte, ikke skjulte resultater fra eksisterende brukere. Resultater fra gamle quizrevisjoner, fremtidige/ugyldige uker og slettede kontoer utelates.

Samlet liste summerer poeng på tvers av uker; samlet tid avgjør ved lik poengsum. Beregningen bruker alle gyldige resultater, også dem som ikke var på ukens topp ti. Identiske fornavn slås ikke sammen; grupperingen bruker konto internt uten å utlevere bruker-ID.

Eksisterende delingsvalg gjaldt bare ukens liste. Ingen blir automatisk lagt i den nye samlede listen. Et separat noncebeskyttet valg lar innloggede medlemmer inkludere eksisterende og fremtidige offentlig delte resultater, eller trekke deltakelsen tilbake. Private og skjulte resultater tas aldri med.

Regresjonstesten dekker dato/revisjon, rangering, totaltelling, private/skjulte/slettede kontoer, samtykke, tilbakekalling og cache. Lokal nettleserkontroll med eksempeldata verifiserte ukevalg og frivillig deltakelse. Mobil390 px: dokumentbredde375 px, tabell440 px i egen305 px rulleflate.

Fire runtimefiler: inc/weekly-quiz.php, ny inc/weekly-quiz-history.php, assets/js/weekly-quiz.js og assets/css/weekly-quiz.css. Ingen eksisterende resultater eller delingsvalg migreres. Selektiv GitHub/SSH-utgivelse krever grønne kildekontroller, fersk baseline, byteverifisert backup og testet tilbakeføring. Publiseringsbevis føres her når det foreligger.
