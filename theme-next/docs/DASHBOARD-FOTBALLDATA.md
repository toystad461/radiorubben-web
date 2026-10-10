# Kamp og kamptropp fra Fotballdata

Dashboardets kampvalg, automatisk valg av neste hjemmekamp og knappen for
spillertropper bruker den eksisterende private Fotballdata-tilgangen.
Tilgang leses fra RRFR_FOTBALLDATA_CID/CWD eller rr_fd_cid/rr_fd_cwd på serveren.
Nøkler og URL-er med tilgang sendes aldri til nettleseren eller feilmeldinger.

Dokumenterte ruter:
- https://api.fotballdata.no/v1/json/metadata?op=Matches
- https://api.fotballdata.no/v1/json/metadata?op=Teams

Kamp og begge tropper hentes fra matches/{id}/people med clubid=827.
Terminlisten hentes fra teams/{30365|48835}/matches. Troppen hentes fra kampen,
slik at en generell spillerliste ikke blir tolket som en bekreftet oppstilling.
De observerte rollene Reserve og Reserve (keeper) er innbyttere; Keeper,
Forsvar, Midtbane og Angrep er startspillere. Ukjente roller eller identiteter,
doble draktnumre og skjulte personopplysninger stopper importen.

Lesekontrollen 10. oktober 2026 bekreftet API-svaret for kamp 8985501:
19 spillere hjemme og 15 borte. JSON-datoer med -0000 representerer i denne
v1-tjenesten norsk klokkeslett uten tidssone: 1791574200000-0000 tilsvarer
9. oktober kl. 19.30, som i lagret FIKS-grunnlag. Adapteren knytter dette
klokkeslettet til Europe/Oslo, også vintertid. Umerkede JSON-datoer tolkes som UTC.
Serverens prøvekjøring sammenligner normalisert kampstart med eksisterende kamp.

Kun nødvendige normaliserte felter kan caches i 30 sekunder. Dommernes og
kontaktpersonenes telefonnummer/e-post forkastes. API-feil gir en feilmelding;
eksisterende kamp, tropp, stemmer og speakerens kampklokke beholdes.
Den eksisterende låsingen av tropp etter åpnet avstemning er beholdt.
Hendelser/resultat og artikkelens tabell/statistikk bruker fortsatt sine
eksisterende separate kilder; denne endringen gjelder kampvalg og kamptropper.

Test: php theme-next/tests/poll-fotballdata.php.
Manuell workflow: Dagens kamp — avgrenset deploy. dry-run validerer en eksakt
patch i fire filer og tester kandidatens API-normalisering. apply tar privat
backup, kontrollerer samtidige endringer, installerer og gjør etterkontroller.
Tilbakeføring skjer automatisk ved feil etter installasjon.
