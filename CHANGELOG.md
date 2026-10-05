# Endringslogg

Alle vesentlige endringer i Radio Rubben-løsningen dokumenteres her.

## [Unreleased]

### Forbedret
- Spillerwidget 1.3.1 leser kampstilling og pauseresultat i eksisterende minuttjobb og deler validerte resultatdata med testvisningen. Se [resultatdata](docs/PLAYER-SCORE.md).
- En felles mobiltilpasset godkjenningsside samler kampomtaler og spillersaker, viser artikkel og valgt bilde først og gjør manuell publisering tydelig. Se [sluttgodkjenning](docs/FOTBALLROBOT-APPROVAL-DESK-20261003.md).
- Fotballroboten kan lenke relevante tekstutdrag direkte til kontrollerte kilder. Lenkeendringer gjennomgår faktakontroll, og kildelisten nederst beholdes. Se [kildelenker](docs/FOTBALLROBOT-INLINE-SOURCES-20261003.md).
- Fotballrobotens kamp- og spillerprompter har en felles Radio Rubben-profil og tydeligere nyhetsvinkel. Spilleromtaler holder fokus på spilleren; statistikkrettelser og gamle historier skal ikke presenteres som nye hendelser. Manuell sluttgodkjenning og separat faktakontroll er uendret. Se [skrivereglene](docs/FOTBALLROBOT-EDITORIAL-PROMPTS-20261003.md).

## [1.0.0] - 2026-09-22

### Lagt til

- Første versjon av nettsiden lagt under tydelig versjonskontroll.
- Node.js-kommandoer for lokal testing, kontroll og bygging.
- Grunnleggende validering av statiske nettsidefiler.
- Dokumentert arbeidsflyt for videre utvikling.
