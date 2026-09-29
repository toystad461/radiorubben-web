# Fotballdata – første integrasjonstrinn

Status: opt-in i kildekoden. Ikke aktivert eller deployet av denne endringen.

## Oppsett
Sett RRFR_FOTBALLDATA_ENABLED til PHP true i privat serverkonfigurasjon.
Sett RRFR_FOTBALLDATA_CID og RRFR_FOTBALLDATA_CWD som private PHP-konstanter eller miljøvariabler.
Ingen faktiske tilganger skal ligge i Git, nettleser, faktapakker eller feilmeldinger.
Utgående URL inneholder leverandørens påkrevde tilgangsparametre; serverens HTTP-/proxylogger må maskere cid/cwd.

## Endret flyt
Fotball.no-kampkortet leses som før. Når integrasjonen er aktivert, kontrollerer Fotballdata kamp-ID,
begge lag-ID-er, turnering, tidspunkt og resultat. Avvik eller API-feil stopper oppdateringen før lagring.
Laghistorikk hentes da fra Fotballdata. Historiske resultater brukes bare når FinalResultApprovedByDistrict
er eksplisitt true. Manglende godkjenning betyr ukjent resultat, ikke 0–0. Manglende eller motstridende
historikk gir eksisterende advarselsflyt og ingen udokumenterte rekker. Ingen stille fallback ved API-feil.
Kampslutt krever fremdeles eksisterende kamparkiv eller administrators bekreftelse.

Dette er en kontrollert delintegrasjon, ikke full erstatning av HTML-kilder.
Hendelser, dommere, lagoppstillinger og spilleroppfølging beholder eksisterende løsning.
Kun normaliserte kampdata lagres. Rå Persons-data, kontaktinformasjon og tilgangs-URL-er lagres ikke.
Godkjenning, e-postoppsett, testpubliseringssperrer og redaksjonell læring er uendret.

## Spillerprofiler – undersøkelse 29.09.2026
Tiril Elisabeth Sellevold-Øystad er identifisert med FIKS-ID 3942773, Brann Kvinner 2 har lag-ID 35897.
Offentlig NFF-profil: https://www.fotball.no/fotballdata/person/profil/?fiksId=3942773
Fotballdata dokumenterer /teams/{teamId}/players og /matches/{matchId}/peopleandevents.
Tilgangen for Brann og responsformatet for spillere må verifiseres før spilleradapter implementeres.
Ingen dokumentert personbasert karrierestatistikk er bekreftet i API-et.
Ikke likestill troppsplass/reserve med spilletid. Ikke utled faktisk alder fra turneringens alderskategori.
Kamp-ID + person-ID må brukes for å hindre dobbeltelling på tvers av lag/turneringer.
Bytte av datakilde må etablere nytt sammenligningsgrunnlag uten historiske varsler.

## Validering
tests/fotballdata.php bruker syntetiske data og mock HTTP. Kontrollerer identiteter, dato,
status, kildekonflikt, historikk, kontaktfiltrering og hemmeligheter i transportfeil.
Kjøring er lagt til WordPress-workflowen.
Levende API ble kontrollert tidligere i samtalen for Bremnes lag, kamper, tabeller og én kamphendelsesfeed.
Nytt Brann-/spillerkall kunne ikke gjennomføres i denne økten. Ende-til-ende WordPress-test gjenstår.
