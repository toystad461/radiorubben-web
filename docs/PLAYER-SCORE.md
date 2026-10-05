# Spillerresultater 1.3.1

Testkortene trenger kampresultat i tillegg til spillerstatus. Widgetens eksisterende minuttjobb leser nå `endResult` og `halfTime` fra den første NFF-kampboksen. Kamp-ID fra `og:url`, begge lag-ID-er og avspark må stemme. Historiske oppgjør, enkeltspillerens mål og klokkeslett brukes aldri til å gjette stillingen. Kun pauseresultat tilgjengelig gir typen `halftime`, også etter at andre omgang har startet. `final` krever eksplisitt sluttmarkering.

Det eksisterende offentlige widget-endepunktet får `matches` med en tillatt feltliste for valgte kamper. Ingen private spillernotater, redaksjonelle hendelser eller feiltekster følger med. Data eldre enn 150 sekunder (900 sekunder etter slutt/avlysning), feilet innhenting eller endret kamptid skjuler stillingen. Nettleseren gjør ingen direkte NFF-kall.

Selektiv utrulling erstatter bare `includes/live.php` og pluginens versjonsfil etter beståtte tester og kontrollsum mot installert 1.3.0. Backup og tilbakeføring er inkludert. Ingen temafiler, robotfiler eller spillerinnstillinger endres. Ikke bruk denne grenen til en bred utrulling eller main-merge: basen inneholder eldre, separat publisert robotarbeid.

Minuttintervallet styres av eksisterende WP-Cron og krever at serverens cron faktisk kjører. NFF kan rapportere forsinket. Grensesnittet skal vise kontrolltidspunkt, merke pauseresultat eksplisitt og skjule foreldet LIVE-status. Den private siden 1154 oppdateres separat etter verifisert utrulling.
