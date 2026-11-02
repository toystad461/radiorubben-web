# RR-WP-TZ-001 – registrering av eksisterende produksjonsendring

Registrert 2. november 2026. Dette arkiverer den allerede autoriserte og verifiserte tidssonerettelsen, ikke en ny utrulling.

- `rr-wp-tz-001-guard.php`: nøyaktig produksjonsfil, SHA256 `fc7f7dd710463e25fabf63af89a39ef33ac980bb5b09734c81db53fe1517529a`.
- `rr-wp-tz-001-test.php`: ni isolerte kontroller. Kjør `php operations/rr-wp-tz-001/rr-wp-tz-001-test.php`.
- `DEPLOYMENT.txt`: historisk leveransekvittering fra før GitHub-registreringen. Dens setning om manglende commit beskriver tidspunktet kvitteringen ble skrevet; denne registreringen lukker mangelen.

Produksjon bruker Europe/Oslo. Tre eksisterende Site Kit-jobber beholdt UTC-tid og argumenter. 1095 postrader beholdt datoer og status. 43 cronregistreringer ble bekreftet. Ingen nye produksjonsendringer eller workflowaktivering inngår i denne PR-en.

Filen ligger i operations som driftsbelegg, uten automatisk pakking/deploy. På serveren ligger den som `wp-content/mu-plugins/rr-wp-tz-001-preserve-sitekit.php`. Manifestet `rr_wp_tz_001_preserved_events` inneholder kun de tre jobbidentitetene og er ikke en hemmelighet.

Etter siste opprinnelige jobb 1. januar 2027 kl. 08.00 norsk tid er beskyttelsen uten effekt. Fjern fil/manifest ved en egen kontrollert opprydding. Ved Site Kit-oppdatering må kompatibiliteten kontrolleres.
