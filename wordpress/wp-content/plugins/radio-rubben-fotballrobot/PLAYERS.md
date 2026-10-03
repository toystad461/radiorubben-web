# Spillere jeg følger — 0.5.0

Felles versjon med spillerfølging og «Lær av mine rettelser» fra 0.4.1. Første versjon av spillerfølging i den eksisterende Fotballrobot-utvidelsen. Denne leveransen er kode og installasjonspakke; den er ikke installert på produksjon.

## Bruk

1. Oppdater den eksisterende utvidelsen med ZIP-pakken. Behold samme plugin-mappe.
2. Åpne **Fotballrobot → Spillere jeg følger** som administrator med rettighet til å redigere innlegg.
3. Legg inn navn og Fotball.no-spillerlenke eller FIKS-ID. Velg hendelser, lokal tilknytning og eventuelt **Bømlo-spillere ute**.
4. Lagre og trykk **Hent spillerdata nå**. Første vellykkede innhenting etablerer sammenligningsgrunnlaget uten varsler om historiske kamper.
5. Nye treff vises med **Se detaljer**, **Lag artikkelutkast** og **Ignorer**. Utkast er dokumenterte arbeidsnotater for redigering, uten AI-kall eller automatisk publisering. Gjentatt klikk åpner samme utkast og overskriver ikke redigeringer.

NFF-klubb hentes fra rollen «Spiller». Et manuelt klubbnotat er separat og utløser ikke klubbendring. Spilleren identifiseres med FIKS-ID, også i kampoppstillinger. Navnelikhet brukes aldri som bekreftelse.

## Dekning og begrensninger

- Sesongrader per lag med kamper, mål, gule og røde kort; endringer inkluderer også rettelser nedover. Null betyr ukjent.
- Inneværende års kampliste hentes fra profilens offentlige `PersonPage/GetMatches`-oppslag. Nye oppføringer er «ny kamp registrert», ikke en påstand om ferdigspilt kamp.
- Oppstillinger og personknyttede hendelser hentes fra kampkort. Kandidater inkluderer lagets kamper siste 30 dager og neste 7 dager når laglisten kan leses.
- Seks kampkort per spillerkontroll, i rotasjon. Full detaljkontroll kan kreve flere kjøringer. Ny klubb uten sesongrader gir ingen lagkandidater før offentlig statistikk finnes.
- En aktiv spiller per time i rotasjon. N spillere trenger minst N timer per runde. WordPress Cron avhenger av trafikk; vanlig serverutløst WP-Cron kan brukes ved behov. Manuell henting er tilgjengelig.
- Kilder mellomlagres i ti minutter. HTTP-feil og endret profilformat beholder tidligere grunnlag; kampfeil vises som varsler. Ukjent klubb/statistikk erstatter ikke siste kjente verdi i sammenligningen.
- Registrert sesongøkning og detaljer fra samme kamp kan gi separate treff. De er ulike kildeobservasjoner, ikke automatisk sammenslåtte mål.
- Bare offentlig publiserte data behandles. Manglende offentlig profil stopper oppdatering. Ingen innlogging hos NFF eller forsøk på å hente skjulte data.

## Arkitektur og senere artikkelrobot

- `PlayerFacts`: ren HTML-adapter, identitetskontroll, normalisering og differanseberegning.
- `Players`: administrasjon, serialisert lagring, innhenting, planlagt kontroll, hendelsesstatus og idempotent WordPress-utkast.
- `players-page.php`: administrasjonsflate.
- Private `rr_robot_player`-poster gir stabile profil-ID-er. Tilstand ligger i `rrfr_player_{id}` med autoload av: profil, gruppe, hendelsesvalg, rå normalisert snapshot, siste kjente sammenligningsverdier, revisjon og hendelser.
- Hendelser har stabil ID, type, før/etter, kilde, kildekontekst, hentetid, FIKS-ID og status `new`, `ignored` eller `draft`.
- Autentiserte administratorruter: GET `/wp-json/rr-fotballrobot/v1/players` og GET `/players/{id}`. Svar er private/no-store. En senere artikkelrobot kan konsumere hendelser og faktakopier uten å kobles til HTML-parseren.
- Artikkelutkast har `_rrfr_player_event` og `_rrfr_player_fact_snapshot`. Eksisterende kampreferatforfatter og publiseringsjobb er uendret.
- Skrivehandlinger krever administrator + edit_posts og WordPress nonce. Eksterne forespørsler bygges bare mot fast Fotball.no-vert; innsendte URL-er brukes aldri direkte.
- Per-spiller lås serialiserer manuell kontroll, cron, redigering og utkast. Etter en hard PHP-avbrytelse blir låsen bevisst stående. Administrator må bekrefte at prosessen har stoppet og kontrollere utkast før `rrfr_player_lock_{id}` fjernes. Globale profillåsen heter `rrfr_player_lock_profiles`.

## Verifisering

Kjør fra utvidelsesmappen:

```sh
php tests/run.php
php tests/writer.php
php tests/writer-flow.php
php tests/report.php
php tests/players.php
php tests/learning.php
php tests/bootstrap.php
```

Spillertestene dekker faktisk NFF-markup hentet 26. september 2026, URL-validering, ID-forveksling, første grunnlag, differanser, lagring, feil, duplikater, låsing, ignorering og idempotente utkast. WordPress-flyten bruker en isolert testdobbel; den erstatter ikke en stagingtest med ekte WordPress, cron og brukerroller.

Administrasjonssiden er kjørt gjennom PHP uten feil. Visuell nettlesertest kunne ikke utføres fordi nettleserverktøyets sikkerhetskontroll var utilgjengelig. Produksjonsinstallasjon og full WordPress-integrasjon gjenstår.

## Tilbakerulling

Installer forrige plugin-versjon og fjern bare cron-hook `rrfr_players_tick` hvis nødvendig. Deaktivering av 0.5.0 fjerner cron-planen. Spillerdata og redaksjonelle utkast beholdes; ingenting slettes automatisk.
