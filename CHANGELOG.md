# Endringslogg

Alle vesentlige endringer i Radio Rubben-løsningen dokumenteres her.

## [site-functions 1.0.0-rc.1] - 2026-09-26

- Fersk produksjonskode sikret i separat funksjonsutvidelse, inkludert dashboard-prototype fra Code Snippets.
- Passiv innlasting med gammelt theme muliggjør kontrollert overgang og tilbakeføring.
- 40 handlingstester og 37 kontraktkontroller på isolert, gjenopprettet lokal kopi; migreringsrapport med eksplisitte gjenstående sluttkontroller.
- Ingen produksjonsdeploy eller private testdata i Git.

## [theme 2.0.0-rc.1] - 2026-09-26

- Nytt presentasjonslag med design tokens, maler, mønstre og utvidbare rr-* bylines.
- Separat metadata-plugin og valgfritt child theme.
- Dokumentert VPS-kontrakt, migreringskrav og faktisk utført lokal QA.
- Egen kontroll-/ZIP-arbeidsflyt uten produksjonsdeploy.

## [1.0.0] - 2026-09-22

### Lagt til

- Første versjon av nettsiden lagt under tydelig versjonskontroll.
- Node.js-kommandoer for lokal testing, kontroll og bygging.
- Grunnleggende validering av statiske nettsidefiler.
- Dokumentert arbeidsflyt for videre utvikling.
# Forsidekladd 2026-10-08

- Avstem syv publiserte forside-/menyfiler mot fersk WordPress-temaklon på egen gren.
- Tilpass nyhetskort til sidefelt, jevn ut avstander og legg til tydelig tastaturfokus på klassisk forside.
- Ingen produksjonspublisering; se `docs/HOMEPAGE-TIDY-2026-10-08.md`.
