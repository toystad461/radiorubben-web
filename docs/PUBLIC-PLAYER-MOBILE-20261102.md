# Mobilcover – 2. november 2026

Rettet og satt i drift på www.radiorubben.no fra `cd852b1df48d257ad8389a012ffe5e16d672212d` (MU-spiller 1.0.4). PR #111 er fortsatt åpen; ikke merget.

Mobiloppsettet skjulte hovedspillerens cover. Minispilleren hadde ikke et bildefelt. Hovedcover vises nå i mobilbredde, og minispilleren får samme cover/logo også på sider uten hovedspiller. Logoen leveres fra aktivt tema. Manglende eller ugyldig cover bruker logo. Ingen lydserver- eller køendring.

Verifisert: metadata-/covertester, minispiller uten hovedkort, reconnect-tester og PHP-lint består. Begge GitHub-kontroller er grønne for kildecommit. Offentlig anonym HTML inneholder nye stilregler og logooppsett. Chrome ved 320 og 390 piksler viser lastet logo, tilgjengelig 44-pikslers play-knapp og ingen horisontal overflyt. Play gir «Du lytter til Radio Rubben». Dette er nettleserkontroll, ikke en ny subjektiv lyttetest på fysisk telefon.

Kun `rr-public-radio.php` og `rr-public-radio.js` er erstattet med fersk hashkontroll, atomisk filbytte og automatisk rollback ved installasjonsfeil. Privat backup: `/home/cptk37ymg_w1417156/.radiorubben-deploy/backups/radio-mobile-w6qcbrk0`, med `before-`-filer og `receipt.json`. Aktivt temas ui.js er uendret. Tilbakeføring: kontroller gjeldende hasher mot receipt og erstatt bare disse to MU-filene med backup, deretter kontroller offentlig spiller.
