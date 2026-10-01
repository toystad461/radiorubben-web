# «Jeg utfordrer deg!» i Ukens Rubben-quiz

## Oppførsel

- Før start, også uten innlogging: del en generell invitasjon.
- Etter levering: del poengsum og tid fra quizens eksisterende serversvar.
- Knappen åpner enhetens delingsmeny. Appene bestemmes av enheten; vi lover
  ikke at en bestemt app, Facebook-tekst eller Instagram Story støttes.
- «Kopier utfordringen» kopierer tekst og lenke. Manglende eller avvist
  utklippstavletilgang gir et markert tekstfelt for manuell kopiering.
- Avbrutt deling gjør ingenting. Delingsfeil gir en forklaring og manuell tekst.
- Navn, konto-ID, nonce, preview-parametere og fasit inngår ikke i delingen.
  Spilleren velger selv å dele. Topplistesamtykket endres aldri.
- Lenken går til `/quiz/` med quizuke og utgave, samt `#rr-weekly`. Gamle
  invitasjoner gir beskjed om at resultatene ikke kan sammenlignes direkte.
- Deling starter aldri en runde. Ingen nye serverhandlinger eller lagrede
  personopplysninger. Knappene skjules mens quizen pågår.
- Dette er deling av en utfordring, ikke registrerte dueller. Resultatet står
  i delingsteksten; det lagres ikke i lenken og mottakeren får ingen automatisk
  duellrangering. Innkommende URL-parametere endrer aldri poeng eller fasit.

## Kontroll og utrulling

Basert på `main` ved `bbcc9e76f844a40961e5a81bdf7b94295eaf22cb`.
Den offentlige `weekly-quiz.js` ble hentet 01.10.2026 og var identisk med
GitHub-versjonen før endring. Dette beviser ikke at all PHP på serveren samsvarer.

Kjør `npm ci`, `npx playwright install chromium`, `npm run test:quiz-share`,
`npm test` og `npm run build`. Nettlesertestene bruker syntetiske spilldata
og mocker delingsmeny, utklippstavle og quizens AJAX-svar. De publiserer ingenting.
Workflowen «Quiz challenge sharing» kjører disse delingstestene på PR-er.

Lokal kontroll 01.10.2026: 16/16 nettlesertester besto i Chromium, inkludert en
hel syntetisk runde, privat/offentlig resultat, avbrudd, feil, kopiering,
ukebytte og 320 px bredde. Mobilvisningen ble også inspisert visuelt.
`npm test`, `npm run build`, JavaScript-syntaks og `git diff --check` besto.
PHP-syntaks kontrolleres av repositoryets eksisterende WordPress-workflow.

Før produksjon:

1. Sammenlign ferske serverkopier av de tre berørte quizfilene med PR-basen.
   Bevar eventuelle nyere endringer, særlig arbeidet med innloggingsretur.
2. Bekreft i staging at en utlogget mottaker havner tilbake på `/quiz/` etter
   Vipps-innlogging, ikke på profilsiden. Den tidligere meldte innloggingsfeilen
   løses ikke av denne endringen. Test også brukernavn/passord.
3. Test den faktiske delingsmenyen på iPhone/Android og lim inn kopiert tekst i
   ønsket sosial app. Ingen innlegg eller meldinger skal sendes automatisk.
4. Rull ut gjennom avtalt GitHub-prosess etter gjennomgang. Tøm eventuell
   sidecache; CSS/JS får en egen versjon for å unngå gamle nettleserressurser.

PR #28 har en egen kopi av quizfunksjonen for det kommende theme-bytte.
Porter de tre quizendringene til den kopien før et slikt bytte; denne PR-en
endrer bare dagens theme. Ingen merge, theme-aktivering eller produksjonsdeploy
er del av denne leveransen.

Tilbakeføring: gjenopprett de tre quizfilene fra verifisert kopi før utrulling.
Ingen databaseendringer må rulles tilbake.
