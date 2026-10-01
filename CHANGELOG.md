# Endringslogg

Alle vesentlige endringer i Radio Rubben-løsningen dokumenteres her.

## 2026-10-01

- Kampforhåndsomtalen bruker motstanderen fra kampens bekreftede hjemme-/borteside i både ingress og historikksøk. Bremnes omtales dermed ikke som sin egen motstander på bortekamp.
- Regresjonstest dekker herre- og damelag hjemme/borte, med og uten importerte kampdata, samt riktig historikk og uendret overskrift.
- Delingsboksen får fire valgbare SoMe-maler: Kampinformasjon, Heia Bremnes, Jeg skal på kamp og Kort til story. Hjemme-/borteteksten følger terminlisten; personlig oppmøte er et aktivt valg, og ingen mal lover åpen avstemming.

## [1.0.0] - 2026-09-22

### Lagt til

- Første versjon av nettsiden lagt under tydelig versjonskontroll.
- Node.js-kommandoer for lokal testing, kontroll og bygging.
- Grunnleggende validering av statiske nettsidefiler.
- Dokumentert arbeidsflyt for videre utvikling.
