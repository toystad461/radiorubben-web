# Automatisk gjenoppkobling for aktive lyttere

Arbeidsområde: Teknisk Produksjon / IT & Utstyr. Kontrollert 01.11.2026, Europe/Oslo.

Installert MU-tillegg 1.0.2 fra `005b9a054ae3882886273668884db594d1f65857`.
Lytteren må først trykke Play. Ved EOF, nettverksfeil eller mer enn 20 sekunder
uten fremdrift kobler spilleren til den levende strømmen igjen. Forsøk venter
3/6/12/24/30 sekunder og har 15 sekunders tilkoblingsfrist. Pause avbryter
gjenoppkobling, også mens en forbindelse forsøkes. Ny sidevisning starter stille.
Hvis nettleseren avviser automatisk lyd med NotAllowedError, vises behovet for Play.
Mobilbakgrunn/dvale er ikke verifisert og kan begrense nettleserens aktivitet.

## Verifisert

- Node-tester: eksisterende nå/neste samt Play, EOF, nettverksfeil, økende
  ventetid, manglende fremdrift, Pause under retry, forsinkede hendelser,
  online-hendelse og nettleserens avspillingssperre.
- PHP-syntaks og begge GitHub-workflows grønne på eksakt installert commit:
  41359120799 og 41359120801.
- Isolert Chrome-test med syntetisk WAV: ett Play, deretter avsluttet lyd og
  to HTTP 503-svar, så faktisk ny avspilling uten nytt klikk. Pause beholdt
  telleren på fire avspillinger og to feil gjennom etterkontrollen.
- Hjemmesiden lastet oppdatert kode, begynte stille, spilte etter Play og
  stoppet etter Pause. Anonym HTML og minifisert JS bekreftet ny controller.
- Ingen VPS-omstart eller avbrudd i offentlig sending ble utført for denne testen.
  Faktisk serveroppstart etter reboot er tidligere dokumentert i Studio PR #115.

## Installering og tilbakeføring

Kun `wp-content/mu-plugins/rr-public-radio.js` og `.php` ble erstattet.
Ferske før-hasher samsvarte med 1.0.1, home/aktivt tema ble kontrollert,
og aktivt temas ui.js beholdt samme hash. Ingen databaseendringer.
Privat backup med verifiserte kopier og kvittering:
`/home/cptk37ymg_w1417156/.radiorubben-deploy/backups/radio-reconnect-zbictvgl`.

Etter-hasher SHA256:
- JS: `bb21d128e6b378001133b3e27e8b070fa5fb01aa95fd6e1c7b795b9d9ba45021`
- PHP: `a04551d30bff64fd4c65c7aba124b514662a5582c46d43f9e04b666c526b0b37`

Rollback: gjenopprett bare `before-rr-public-radio.js` og
`before-rr-public-radio.php` fra denne backupen til tilsvarende MU-filer,
kontroller PHP og regenerer minify-cache ved behov. Før-hasher står i receipt.json.
Den gamle versjonen krever Play etter serveravbrudd.
PR #111 er fortsatt draft og ikke merget. Eksisterende åpne sider trenger
én oppdatering for å laste inn 1.0.2.
