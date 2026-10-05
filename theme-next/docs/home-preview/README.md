# Testforside med eksempelinnhold

Forhåndsvisningen bruker de faktiske forside-, header-, bunntekst- og spillerkomponentene, med syntetiske artikler, kampdata og sendestatus. Bildene er temaets eksisterende Mosterhamn- og fotballressurser. Ingen sending, produksjonsdatabase eller robot brukes i testen.

`layout-check.json` er lokal kontroll i Edge/Chromium ved 320, 390, 768 og 1440 px for radio, nyheter og sport som hovedinngang. Alle tolv tilfeller kontrollerer rekkefølge, overflyt, bildeinnlasting, én felles lydmotor, mobilmeny/Escape og gjenopprettede kontroller etter avvist avspilling.

Skjermbilder og ferdige staging-ZIP-er blir også generert i GitHub Actions-artefakten `home-universes-staging-<commit>`. De er merket testvisning og erstatter ikke WordPress-staging eller ekte lydtest.

Lokal gjentakelse fra repository-roten med PHP, Node og Playwright tilgjengelig:

```sh
php theme-next/tests/home-universes.php
php theme-next/tests/home-lint.php
for scenario in radio news sport; do
  RR_PREVIEW_SCENARIO="$scenario" php theme-next/tests/home-preview.php > "preview-$scenario.html"
done
python3 -m http.server 12379 --bind 127.0.0.1
# I en separat terminal, med Playwright installert og Chromium tilgjengelig:
node theme-next/tests/home-browser.cjs
```

Åpne `http://127.0.0.1:12379/preview-radio.html`. Testvalgene øverst viser de tre prioriteringene. Play-knappene bruker temaets ekte hendelseshåndtering, men lydforsøket avvises bevisst av fixture-en, uten kontakt med en radiostrøm.
