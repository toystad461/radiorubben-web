# Lokale sidemalprøver

Fire maler, 320/390/768/1440 px. PNG ved 390 og 1440 px og `layout-check.json` er testbevis, ikke produksjon eller faktisk WordPress-editor. Header/footer i denne fixture er forenklet; den eksisterende forsidetesten kontrollerer de ekte felles delene. Innhold og pluginrespons er eksempler.

Sett `RR_WP_INCLUDES` til en lokal WordPress `wp-includes`-mappe (bare blokkparseren lastes; aldri databasen), kjør `php theme-next/tests/page-layouts.php`, og generer de fire HTML-ene med `RR_LAYOUT=standard|application|editorial|football php theme-next/tests/page-preview.php`. Workflowen viser fullstendige kommandoer og pakker.
