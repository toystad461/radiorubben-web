## 05.11.2026 – klokkeavvik bekreftet; rettelse under kontroll

Thomas sin nettlesermåling ga HTTP 200 på 556 ms, status playing,
PC-tid 2026-11-05T14:01:44.306Z og server-tid 14:01:13Z:
31,306 sekunders forskjell (15:01 norsk tid). Begge API-er leverer gyldige
nå/neste-data. Den eksisterende klientkontrollen avviser dette: Studio ved
5 sekunders forskjell, offentlig spiller ved 30 sekunder. Tester gjenskaper
begge feiltekstene med samme avvik. Målingen fastslår relativt avvik, ikke
hvilken maskin som har korrekt absolutt tid.

Rettelsen binder hvert svar til en tilfeldig forespørsels-ID og bruker
performance.now() for svartid og lokal utløpstid. Serverens kontroll av
hjerteslag og forbindelse beholdes. Gamle eller forsinkede svar avvises,
og svar fra før et fanebytte får ikke fylle visningen etterpå.
Gamle API-kall uten requestId fungerer fortsatt. Private lytterrettigheter
og offentlig feltbegrensning beholdes. Ny klient krever ny serverrute.

Koden er klargjort i #115 og web #111; installasjon og brukeretterkontroll
gjenstår. Ingen lydmotor, sendekø, bibliotek eller WordPress-innhold endres.


Utrullingsrekkefølge: først Studios to statusruter, deretter Studio-klient og
de to eksisterende WordPress-MU-filene (versjon 1.0.6). Bare disse målene;
aldri hele grenen, temaet eller WPVibe-utkastet. Ta privat kodebackup,
kontroller ferske før-hasher og bevar nyere endringer før filbytte.

Lokale Node-tester dekker det observerte klokkeavviket, +/-1 time/døgn,
replay/feil forespørsels-ID, tregt svar, utløp og ny henting ved fanebytte.
Eksisterende cover-, lytte-, Pause- og gjenoppkoblingstester er bevart.
PHP og CI, selektiv installasjon og faktisk PC/iPhone-etterkontroll må
dokumenteres separat før leveransen ferdigmeldes.
