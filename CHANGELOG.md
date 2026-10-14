# Endringslogg

Alle vesentlige endringer i Radio Rubben-løsningen dokumenteres her.

## [rr-news-images 1.0.0] – 2026-10-14 – ikke aktivert

- Verifisert import av RadioRubben-Nyhetsbilder-v1.2 med stabil asset-identitet, kontrollsummer og gjenopptakelse uten duplikater.
- Autentisert katalog med faktiske WordPress-medie-ID-er/URL-er til Studios eksisterende godkjenningsflyt.
- Bevaring av redaksjonelle bilder, kontrollert KI-proveniens, hele originaler og synlig KI-bildetekst.
- Isolerte WordPress-import-/publiseringstester og mobil-/skrivebordskontroll; produksjonstema/CDN og aktivering gjenstår.

Se [nyhetsbilder](wordpress/wp-content/plugins/rr-news-images/README.md). Ingen produksjonsendring.

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
