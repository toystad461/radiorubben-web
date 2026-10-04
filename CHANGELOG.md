# Endringslogg

Alle vesentlige endringer i Radio Rubben-løsningen dokumenteres her.

## Publisert – 2026-10-04

- Egen WordPress-plugin for Bømlo-spillernes kamper: godkjent Radio Rubben-utforming, lagvalg per spiller, kontrollert MyGame/TV 2 Play-lenke og separat troppsstatus.
- Kortkode og vanlig WordPress-widget, bakgrunnsinnhenting og automatiske sperrer mot utdaterte sendingslenker.
- Spillerkamper 1.0.1 publisert selektivt på forsiden (to kort) og Sport-siden (inntil seks kort), med fem spillere og fire kontrollerte lag. Ferske NFF/MyGame-oppslag fra webhotellet bestod, inkludert dagens Brann 2-kamp og Tirils oppføring som innbytter.
- Sidene med kampoversikt unntas fra fullsidecache. Fotballroboten er uendret; aktiv forside fikk ett avgrenset widget-hook med sikkerhetskopi. Publisering fra ed33789911f21a0e11edff732cf71c8ed8c36199; GitHub PR #45 er fortsatt åpen uten bred main-deploy.

## [1.0.0] - 2026-09-22

### Lagt til

- Første versjon av nettsiden lagt under tydelig versjonskontroll.
- Node.js-kommandoer for lokal testing, kontroll og bygging.
- Grunnleggende validering av statiske nettsidefiler.
- Dokumentert arbeidsflyt for videre utvikling.
