# Radio Rubben Next — 2.0.0-rc.1

Installérbar stagingkandidat. Ikke godkjent for produksjonsaktivering ennå.

**Ny i theme-verdenen? Start med [brukerveiledningen for Thomas](BRUKERVEILEDNING.md).** Den forklarer hva som følger med ved theme-bytte, hva som må sikres og hvordan overgangen testes.

## Kildekode

- `radio-rubben-next/`: hovedtheme; kun presentasjon.
- `rr-editorial-contract/`: valgfri separat metadata-/REST-plugin.
- `radio-rubben-child/`: child theme for lokale tilpasninger.
- `radio-rubben-next/docs/`: malbruk, rr-* kontrakt, VPS-migrering, QA og krav før produksjonsbytte.
- `scripts/`: kontroller og ZIP-bygg.
- `tests/integration.php`: WordPress-integrasjonstest for disponibel lokal database.

Denne mappen ligger bevisst utenfor `wordpress/`. Eksisterende publiseringsskript tar den ikke med. Endringer her skal gjennom pull request og staging før en egen, eksplisitt beslutning om produksjonsmigrering.

## Kontroller og bygg

Fra repositoryets rot, med PHP 8.2+, Python 3 og Node.js:

```sh
python3 theme-next/scripts/check.py
python3 theme-next/scripts/package.py
```

ZIP-er og SHA-256-manifest genereres i `theme-next/dist/` og lagres også som nedlastbart GitHub Actions-artifact etter bestått kontroll. De genererte filene versjonskontrolleres ikke.

Den automatiske GitHub-kontrollen kjører syntaks-, struktur-, grense- og pakkekontroller. Den erstatter ikke WordPress-runtime, visuell kontroll eller staging med faktiske integrasjoner. Se [utført QA](radio-rubben-next/docs/QA.md).

## Lokal integrasjonstest

Bruk en ny, disponibel WordPress-installasjon, aldri produksjonsdatabase eller en kopi med persondata. Sett `WP_ENVIRONMENT_TYPE` til `local`, installer og aktiver hovedtheme og metadata-plugin, og installer Twenty Twenty-Five. Testen oppretter og sletter testbrukere/innlegg, bytter theme og endrer testinnstillinger.

```sh
RR_DISPOSABLE_TEST_DB=1 wp --path=/path/to/disposable-wordpress eval-file theme-next/tests/integration.php
```

Lokale databaser, WordPress-kjerne, referansebackuper og midlertidige testdata inngår ikke i repositoryet. Den tidligere lokale `qa/`-mappen følger ikke med.

## Arbeidsflyt

Lag gren → endre kanonisk kildekode → kjør kontroller → pull request → gjennomgang → slå sammen. Versjonér theme/plugin separat ved neste utgivelse. Bevar metadata-kontrakten når motorer senere flyttes til VPS. Ingen API-nøkler eller produksjonsinnlogginger skal inn i kildekoden.
