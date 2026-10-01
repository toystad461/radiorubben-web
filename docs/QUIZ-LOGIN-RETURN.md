# Retur til quiz etter Vipps-innlogging

## Feil og avgrensning

Rapportert 1. oktober 2026: Vipps-innlogging fra quiz ender på profilsiden.
Den offentlige quizknappen kaller `login_with_vipps("wordpress")` uten ekstra
data. Dagens `rr_quiz_vipps_return()` bruker `referer` fra Vipps-økten;
manglende eller feil referer gjør at Vipps beholder standarden profilsiden.
Den konkrete mobiløkten er ikke undersøkt, så tapt HTTP Referer er en
reprodusert svakhet, ikke en bekreftet analyse av brukerens økt.

## Retting

- Quizknappen sender en uttrykkelig `_wp_http_referer` med målet
  `https://www.radiorubben.no/quiz/#rr-weekly` i Vipps-pluginens vanlige POST.
- WordPress `wp_get_raw_referer()` leser dette feltet; Vipps lagrer det i sin
  eksisterende innloggingsøkt. Den eksisterende serverregelen validerer
  vert/quizsti og konstruerer den lokale returadressen.
- Ingen endring i autentisering, rettigheter, quizforsøk eller resultater.
  Innlogging starter ikke tidtaking.
- Preview-parameter bevares. Vanlig brukernavn/passord får samme quizanker.
- Ny versjonsstreng for JavaScript hindrer gjenbruk av gammel fil etter deploy.

## Avgrenset produksjonsretting

`wordpress-content/quiz-vipps-return-20261001.html` er en midlertidig,
kildestyrt HTML-blokk for eksisterende WordPress-side 496 (`/quiz/`). Den
settes inn rett før blokken med `anchor: rr-live-quiz`. Den virker med
dagens theme og fanger bare Vipps-knappen inne i den ukentlige quizen.
Capture-handleren hindrer dobbelt innloggingskall dersom theme-rettingen
og innholdsrettingen finnes samtidig.

Denne innholdsrettingen krever ikke publisering av WPVibe-temautkastet eller
utestede øvrige theme-endringer. WordPress-revisjonen bevarer forrige innhold.
Tilbakeføring: fjern akkurat denne HTML-blokken og tøm sidecache.

## Permanent utrulling

Ved neste kontrollerte kodeutrulling, sammenlign fersk produksjonskode og
overfør endringene i `inc/weekly-quiz.php` og `assets/js/weekly-quiz.js`.
Ikke last opp hele main-grenen: repoets kildeimport er eldre enn deler av
produksjonen. Fjern innholdsblokken først etter at permanent retur er verifisert.
PR #28 har egne kopier i `rr-site-functions`; rettingen må overføres dit før
eventuelt theme-bytte. Dette arbeidet aktiverer ikke Radio Rubben Next.

## Verifisering

- JavaScript: fire automatiske tester for manglende Referer, preview,
  vanlig passordlenke, manglende Vipps/config og ingen start av quizklokken.
- PHP: 13 kontroller av returregelen, ArrayAccess-økter, normal innlogging,
  kontobekreftelse, feiltilfeller og avvisning av eksterne mål; kjøres i Actions.
- Den midlertidige innholdsblokken er kjørt mot den offentlig hentede
  JavaScript-filen fra produksjonens Login with Vipps 1.5.6, med simulert
  jQuery-transport: fire kontroller besto, inkludert POST-payload og at andre
  lenker ikke fanges. Ingen ekte Vipps-innlogging eller quizinnsending utført.
- Manuell sluttkontroll: åpne `/quiz/` utlogget på mobilen, godkjenn Vipps og
  bekreft retur til quizen før Start quizen. Prøv også vanlig Min side-innlogging.
