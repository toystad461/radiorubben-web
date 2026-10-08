# Forsideopprydding – 8. oktober 2026

Kildegren: `codex/homepage-tidy-20261008`, basert på PR #67 / `210461622b790980fb57d34d3f12b53212bb3a51`.

## Fersk, avgrenset kildeavstemming

WPVibe bekreftet aktivt tema `radio-rubben-next` 2.0.0-rc.5. Et nytt utkast ble klonet fra dette temaet; ingen filer ble redigert på WordPress. Syv forside-/menyfiler ble lest fra den ferske klonen og lagret i commit `14995a0`. De inneholder allerede publisert sidefelt, vær, medlemslenke og enklere meny, som manglet i PR #67. Den offentlige DOM-en bekreftet oppsettet.

Leseverktøyets linjenumre er fjernet, og Git normaliserer linjeskift. Dette er kildeavstemming av syv filer, ikke byteidentiske serverhasher eller en full produksjonsinventering. Øvrig tema, plugins, driftsdata og åpne kontroller i PR #67 er ikke avstemt her. Eksisterende lokale ZIP-er er ikke brukt som produksjonsfasit.

## Visuell endring

Et separat stilark for klassisk forside gir jevnere avstander, tilpasser overskrifter og nyhetskort til hovedkolonnen med sidefelt, samler kortradier og viser tydelig tastaturfokus. Mobil beholder nyhetene før sidefeltet. Ingen ny skjuling av nyheter, kampdata eller værinformasjon.

Kun `front-page.php` og `assets/css/homepage-refinement.css` er nye utrullingskandidater mot dagens leste WordPress-grunnlag. De øvrige runtimefilene i første commit dokumenterer det eksisterende oppsettet. Historiske manifesttre i `production-candidate.json` gjelder foreldregrunnlaget, ikke dette nye tematreets identitet.

## Kontroll og publisering

Eksisterende nyhetskontrakt er oppdatert for det publiserte sidefeltet og stilrekkefølgen. GitHub CI kjører PHP-, kilde- og syntetiske skjermkontroller. Lokal og GitHub teststatus rapporteres i PR-en; ingen grønn status skal antas før kjøringene er ferdige. Full visuell kontroll av ekte WordPress-utkast på mobil og desktop gjenstår.

Ingen main-merge, produksjonsdeploy, WPVibe-filskriving, innholdsendring eller pluginendring er utført. Før publisering må de to kandidatfilene sammenlignes på nytt med produksjon, utkastet inspiseres og tilbakeføring verifiseres etter AGENTS.md. Produksjonsavstemmingen i PR #67 beholdes separat.
