# Radio Rubben Next 2.0.0-rc.1

Installérbart **hybridtema / stagingkandidat**, bygget på Radio Rubben One 1.3.6 og dagens offentlige CSS. PHP-maler er bevisst valgt for eksisterende kortkoder, `the_content`-filtre, RRLive og WordPress-URL-er. `theme.json` v3 styrer palett, typografi, mål og mellomrom; blokkeditoren får syv mønstre og panelstil. Dette er ikke et fullstendig blokkthema / Site Editor-tema.

## Viktig før installasjon

Les [DEPLOY](docs/DEPLOY.md). Dagens theme inneholder applikasjonskode. Dette nye temaet inneholder ikke denne koden, og er derfor **ikke et ferdig verifisert bytte for produksjon**. Temaet fungerer alene for ordinære sider og artikler. Kamp, medlemskap, vær og quiz trenger sine eksisterende motorer flyttet til egne funksjonsutvidelser. De separate utvidelsene som allerede finnes skal beholdes.

## Innhold

- [Mappestruktur og malbruk](docs/TEMPLATES.md)
- [rr-navnerom, journalister og komponentkontrakt](docs/RR-CONTRACT.md)
- [Senere VPS-integrasjon](docs/VPS.md)
- [Avhengighetskart, trygg utrulling og tilbakeføring](docs/DEPLOY.md)
- [Kilder og visuell fasit](docs/REFERENCES.md)
- [Teststatus](docs/QA.md)

`rr-editorial-contract` leveres som **egen ZIP**, og registrerer varige byline-/kildemetadata, REST-tilgangskontroll og en redigeringsboks. Temaet leser feltene; det registrerer ikke datafelter, REST-ruter, cron, innholdstyper eller brukerroller. Det foretar ingen AI-kall og skriver ingen innlegg.

Behold temaets mappe `radio-rubben-next`. Tilpasninger legges i et child theme eller en funksjonsutvidelse. Ikke erstatt den gamle mappen `radio-rubben-wordpress-v1` med denne uten fullført funksjonsmigrering.
