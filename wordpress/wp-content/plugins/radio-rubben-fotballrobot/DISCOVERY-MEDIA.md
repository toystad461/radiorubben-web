# Spillere ute: kandidater og mediesaker (0.9.6)

Spillere med tidligere Bømlo-klubb og dokumentert aktivitet utenfor Bømlo foreslås i **Fotballroboten → Spillere ute – kandidater**. Redaktøren velger **Godkjenn og følg**, **Se senere** eller **Avvis**. Avviste forslag kan åpnes igjen. Godkjenning oppretter én privat spillerprofil i `bomlo-away`; eksisterende profiler endres ikke. Historiske klubbskifter oppretter ingen nyhet eller artikkel. Første spillerkontroll lager utgangspunktet for senere endringer.

Søkevinduet dekker sesonger fra inneværende år minus 20 til inneværende år. Status viser alltid `coverage=partial`: enkeltfunn er dokumentert, ikke en garanti for komplett overgangshistorikk. Bremnes, Moster, Finnås, Bømlo og Rubbestadneset er lokale klubber; interne klubbskifter er ikke kandidater til Spillere ute. Aktivitet i inneværende/foregående sesong og dagens klubb må dokumenteres. Ingen terskel for divisjon er automatisk godkjenning; redaktøren velger relevansen.

Den eksisterende daglige researchoppgaven søker offentlig tilgjengelige klubbsaker, lokalmedier og tilgjengelig historikk, og leverer kontrollerte fakta til pluginens private API. Denne endringen innfører ingen overgangscrawler eller tilgang til et nytt NFF-API. Respekter kildevilkår, betalingsmurer og tekniske tilgangsgrenser. Profil- og kampsider er ikke i seg selv bevis for komplett historikk. Manglende eller foreldet dokumentasjon rapporteres som hull.

## Privat kandidatinnboks

`GET /rr-fotballrobot/v1/player-candidates` gir søkevindu, dekningsforbehold, status og kilder. `POST` på samme rute krever administrator og følgende JSON:

```json
{
  "fiks_id": 1234567,
  "name": "Fullt kontrollert navn",
  "former_club": "Bremnes",
  "former_seasons": [2017, 2018],
  "current_club": "Dokumentert nåværende klubb",
  "current_level": "Dokumentert lag og nivå",
  "active_season": 2026,
  "history_note": "Kort dokumentert klubbhistorikk. Skill gamle overganger fra nye.",
  "checked_at": "2026-10-04T12:00:00Z",
  "sources": [
    {"kind":"history","url":"https://example.org/historikk","fact":"Dokumentert Bømlo-bakgrunn.","public_read":true},
    {"kind":"current","url":"https://example.org/spiller","fact":"Dokumentert klubb nå.","public_read":true},
    {"kind":"activity","url":"https://example.org/sesong","fact":"Dokumentert aktivitet denne sesongen.","public_read":true}
  ]
}
```

Eksemplene er skjemaillustrasjoner, ikke ekte spillere eller kilder. `checked_at` må være høyst to døgn gammel. Det kreves tre beviskategorier; samme profilside kan dokumentere flere kategorier. FIKS-ID er identitet, aldri navnelikhet alene. Gjentatt levering returnerer eksisterende status og endrer ikke redaktørens valg. REST har ingen godkjenningsrute. Godkjenningsskjemaet krever rettighet, nonce og gjeldende versjon. Beslutninger får tidspunkt og bruker-ID.

## Fordypningssak med originalvideo

Den eksisterende ruten `POST /rr-fotballrobot/v1/players/{id}/sources` brukes bare for en allerede godkjent og aktiv spiller. De eksisterende feltene (`fiks_id`, `public_read`, `url`, `title`, `identity_note`, `published_at`, `checked_at`, `facts`, `event_date`) beholdes. Nye valgfrie felt:

- `format`: `brief` (standard) eller `feature`.
- `video`: `{url, publisher, content_verified: true}`. URL skal være den opprinnelige mediesiden med intervjuet og være lik hovedkildens URL. `content_verified` betyr at innholdet faktisk er kontrollert, via videoen eller et pålitelig tilgjengelig transkript. Tittel, søketreff og en avspiller alene er ikke nok.
- `supporting_sources`: høyst fem `{kind, url, title, public_read:true, checked_at, event_date, facts}`. `kind` er `match`, `background`, `table` eller `club`; ukjent hendelsesdato kan være `null`. Hver kilde har 1–6 faktapunkter i egne ord og høyst 120 ord totalt.

En `feature` krever verifisert video, separat kampkilde og separat bakgrunnskilde, minst to nettsteder og minst seks faktapunkter totalt. Mangler dette, skal research innhente mer eller melde hva som mangler. Ikke sende et tynt grunnlag som full videosak. For `serieleder` kreves dokumentasjon av tabellplass før den aktuelle kampen.

Skriveren sikter mot 250–400 ord og 4–7 avsnitt når fakta bærer lengden, ellers kortere. Teksten skal være original og navngi mediet. Ingen direkte sitater fra mediet; verifiserte uttalelser gjengis kort i egne ord. Høyst 120 ord av artikkelen kan bygge på én mediekilde. Kamp- og spillerbakgrunn må ha egne kilder. Alle lenker knyttes til lagrede kilder; systemet legger alltid til **Se hele videointervjuet hos [medium]** og full kildeliste. Video og bilder kopieres ikke. Den separate faktakontrollen vurderer kildekobling, attribusjon, tabelltidspunkt og om mediesaken gjenfortelles for omfattende.

Research leverer bare kilder. Eksisterende skrivekø oppretter utkast med faktakontroll og språkvask. Endelig publisering krever redaktørens sluttgodkjenning av den aktuelle teksten. Feil i skriving eller kontroll gir ikke automatisk nytt betalt forsøk.

## Kontroll og aktivering

`tests/candidates-media.php` tester ekte kandidat-/profilpersistens, identitetsduplikater, avvisning/utsettelse, gamle beslutninger, tilgang, lokale klubbskifter, dokumentasjonskrav, upålitelig video, artikkellenker og ny kontroll av lagret HTML. Eksisterende testsuiter for skriving, publiseringsvern og godkjenningsflyt kjøres sammen med denne.

Den avgrensede release-jobben berører ni runtimefiler. Før utskifting kreves nøyaktig SHA-256-samsvar med den kjørende 0.9.5-koden. Den tar kodebackup og sammenligner fingeravtrykk av eksisterende spillerprofiler, kilder, kandidater, artikler og robotinnstillinger før/etter. Feil ruller koden tilbake. Nye kandidater leveres separat etter bekreftet aktivering, og ingen godkjennes av utrullingen.
