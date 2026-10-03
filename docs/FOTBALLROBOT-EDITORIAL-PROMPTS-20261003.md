# Radio Rubben: skriveregler 2026-10-03.1

## Endringen
Match- og spillerforfatteren deler nå en tydelig Radio Rubben-profil: naturlig bokmål, varme gjennom konkrete detaljer, én dokumentert hovedvinkel, korte avsnitt og høyst to relevante bakgrunnspoenger.

Spillersaker tar utgangspunkt i én ny opplysning om den riktige spilleren. Andre spilleres kamphendelser brukes bare når de forklarer denne nyheten. En statistikkrettelse eller en nylig innlest kilde er ikke i seg selv en ny sportslig hendelse. Dokumentert Bremnes-tilknytning kan gi lokal sammenheng; navn og private familieforhold kan aldri gi den.

Redaktørens «mer journalistisk formidling» er konkretisert som en fast skriveregel. Andre kommentarer til én tekst gjelder fortsatt bare den teksten. Tidligere tekster og godkjente eksempler gir språkveiledning, ikke nye fakta.

## Teknisk omfang
- Kun `includes/writer.php` endres i kjøretid.
- `Writer::WRITING_PROMPT_VERSION`: `2026-10-03.1`.
- Egen `playerPrompt()` og felles `editorialProfile()`.
- Faktakontroll, språkvask, ny faktakontroll ved endring og manuell sluttgodkjenning består.
- Kvalitetsreglene beholder versjon `1.0.0`: en justering av skrivestil skal ikke ugyldiggjøre eksisterende godkjenninger.
- Ingen omskriving av lagrede innlegg eller prøveutkast, ingen betalte AI-kall eller endring av modell, kilder, bilder, køer eller tidsplan.

## Verifikasjon
`tests/writer-flow.php` bruker den virkelige spillerforfatteren og simulerte API-svar. Testen følger skriving, uavhengig kontroll, språkvask og kontroll av endret tekst; redaktørkommentar og tidligere tekst følger ikke med som kontrollgrunnlag. Et avvist kontrollresultat etter omskriving blokkerer fortsatt godkjenning.

Automatiske tester kan kontrollere flyten, ikke garantere den journalistiske kvaliteten på neste modelltekst. Neste ordinære utkast må fortsatt vurderes av Thomas.

## Aktivering
Endringen leveres separat fra tidligere umergede funksjonsgrener. En eventuell aktivering må erstatte bare forfatterfilen, med kontroll av eksisterende filhash, sikkerhetskopi og tilbakeføring ved feil. Hele grenen skal ikke distribueres til produksjon.

## Aktivert 3. oktober 2026

Skrivereglene ble aktivert kl. 21.35 norsk tid fra commit `1a8bddef1d64777ba70b88716e5a1a13585c6f7f`. Bare `includes/writer.php` ble erstattet, etter kontroll av gammel filhash og sikkerhetskopiering. Før-/etterkontrollen bekreftet uendrede lagrede spillerdata og spillerutkast. Fem aktive FIKS-profiler og krav om manuell sluttgodkjenning ble kontrollert på nytt.

Alle 13 PHP-testprogrammene og syntakskontrollen besto. Ingen betalte AI-kall, prøveomskrivinger eller artikkelpubliseringer ble utført.

- [Aktivering og kontroller](https://github.com/toystad461/radiorubben-web/actions/runs/37148398186)
- [Integrasjonstest](https://github.com/toystad461/radiorubben-web/actions/runs/37148401431)
- [Endringsforslag #42](https://github.com/toystad461/radiorubben-web/pull/42)
