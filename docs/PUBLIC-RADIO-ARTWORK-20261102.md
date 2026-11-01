# Cover med Radio Rubben-logo som reserve

Installert 02.11.2026 Europe/Oslo: MU-tillegg1.0.3 fra d42fb72.
Spillerkortet viser now.artworkUrl fra offentlig Studio-status når gyldig.
Kun eksakt HTTPS-rute på5.249.252.78/radio-status.php?cover=<64 hex> tillates.
Manglende/ugyldig cover, jingle, foreldet/offline status eller bildefeil bruker
temaets eksisterende rr_one_logo_url-bilde. Avspillingskontroller er uendret.

96 aktive filer ble undersøkt på VPS: 95 mangler bilde, ett har ødelagt APIC.
Derfor vises logo for alle nåværende spor. Ingen tredjepartssøk etter bilder.
Nye filer med gyldig innebygd JPEG/PNG klargjøres automatisk av Studio-jobben.

Node-tester for metadata, gjenoppkobling, cover-URL og logo-fallback består.
Begge Web CI-workflows er grønne på d42fb72. Anonym minifisert bundle er
kontrollert, og logoen lastet korrekt i faktisk Chrome på hjemmesiden.
Kun to MU-filer erstattet, med ferske før-hasher og uendret aktivt tema.

Privat backup/receipt: /home/cptk37ymg_w1417156/.radiorubben-deploy/backups/radio-artwork-aor_yfvq.
Rollback: gjenopprett before-rr-public-radio.js og before-rr-public-radio.php,
regenerer minify-cache ved behov. Ikke endre database, tema eller sendekø.
Serverdetaljer: Studio docs/STUDIO-PUBLIC-ARTWORK-20261102.md i PR115.
Web PR111 er fortsatt draft, ikke merget.
