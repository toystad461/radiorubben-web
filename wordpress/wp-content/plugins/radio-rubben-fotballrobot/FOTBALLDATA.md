# Fotballdata – Bremnes-kampdata

Status: opt-in i kildekoden. Ikke aktivert eller deployet av denne endringen.

## Oppsett
Sett RRFR_FOTBALLDATA_ENABLED til PHP true i privat serverkonfigurasjon.
Sett RRFR_FOTBALLDATA_CID og RRFR_FOTBALLDATA_CWD som private PHP-konstanter eller miljøvariabler.
Ingen faktiske tilganger skal ligge i Git, nettleser, faktapakker eller feilmeldinger.
Utgående URL inneholder leverandørens påkrevde tilgangsparametre; serverens HTTP-/proxylogger må maskere cid/cwd.

## Endret flyt
Når integrasjonen er aktivert, hentes kampkort, kamphendelser og dommere fra Fotballdata.
De to kampendepunktene må stemme overens om ID, begge lag, turnering, avspark og resultat.
Avvik eller API-feil stopper oppdateringen før lagring. Lagform hentes fra turneringens
kampliste, slik at motstanderens kamper kan leses innenfor Bremnes-lisensen. Historiske resultater brukes bare når FinalResultApprovedByDistrict
er eksplisitt true. Manglende godkjenning betyr ukjent resultat, ikke 0–0. Manglende eller motstridende
historikk gir eksisterende advarselsflyt og ingen udokumenterte rekker. Ingen stille fallback ved API-feil.
Kampslutt krever fremdeles eksisterende kamparkiv eller administrators bekreftelse.

Pausemål finnes ikke eksplisitt i det kontrollerte API-svaret og lagres som ukjent. Eksisterende
lagoppstillinger på Dagens kamp kan brukes når navnene samsvarer. Spilleroppfølging utenfor
Bremnes og sesongstatistikk på personprofilnivå beholder Fotball.no.
Kun normaliserte kampdata lagres. Rå Persons-data, kontaktinformasjon og tilgangs-URL-er lagres ikke.
Godkjenning, e-postoppsett, testpubliseringssperrer og redaksjonell læring er uendret.

## Spillerprofiler – undersøkelse 29.09.2026
Tiril Elisabeth Sellevold-Øystad er identifisert med FIKS-ID 3942773, Brann Kvinner 2 har lag-ID 35897.
Offentlig NFF-profil: https://www.fotball.no/fotballdata/person/profil/?fiksId=3942773
Fotballdata dokumenterer /teams/{teamId}/players og /matches/{matchId}/peopleandevents.
Kontroll 30.09.2026: /teams/35897/players og /teams/35897/matches svarte 403 med denne CID/CWD.
Viggo og Arna-Bjørnar 2 sine lagendepunkter svarte også 403, mens turnering 205982/matches
med clubid=827 svarte 200. Leverandøren må utvide tilgangen før Brann-spilleroppfølging kan flyttes.
Ingen dokumentert personbasert karrierestatistikk er bekreftet i API-et.
Ikke likestill troppsplass/reserve med spilletid. Ikke utled faktisk alder fra turneringens alderskategori.
Kamp-ID + person-ID må brukes for å hindre dobbeltelling på tvers av lag/turneringer.
Bytte av datakilde må etablere nytt sammenligningsgrunnlag uten historiske varsler.

## Validering
tests/fotballdata.php bruker syntetiske data og mock HTTP. Kontrollerer identiteter, dato,
status, kildekonflikt, historikk, kontaktfiltrering og hemmeligheter i transportfeil.
Kjøring er lagt til WordPress-workflowen.
Levende API ble kontrollert for Bremnes kamp 8985491 og turnering 205982. Syntaks og mocktester
kjører i GitHub Actions. Produksjon har plugin 0.9.0, mens denne stablede grenen bygger på 0.6.0;
aktiv plugin må sammenlignes før utrulling. Ende-til-ende WordPress-test gjenstår.
