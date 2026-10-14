# PR30: Bremnes G13/J13+ og søndagsoversikt

PR30s opprinnelige 0.6.1-kode er tilpasset den dokumenterte produksjonsversjonen
0.10.4, rekonstruert byteidentisk i `75326d1c26cd3b30f7c52ead8246b4e99df950a4`.
Kandidaten er 0.10.5. Den gamle branchen deployes aldri som en full WordPress-pakke.

- Nye rådata hentes fra Bremnes' klubbendepunkter hos Fotballdata. Lag avgrenses
  med registrerte lag-ID-er og aldersklasser: gutter og jenter fra 13 år. Kontaktfelt
  og private tilgangsparametre tas ikke med i lagrede fakta, modellkall eller logger.
- Ungdomsreferat blir tidligst klargjort når et uttrykkelig bekreftet sluttresultat
  har vært uendret i én time. Dette følger den nyere redaksjonelle ventetiden i
  seniorroboten. Kampene må ha avspark etter aktivering; ingen historisk masseproduksjon.
  Seniorreferater beholder sin eksisterende jobb.
- Søndag klokken 18:00 i Europe/Oslo starter en ukesartikkel om kommende mandag–søndag.
  Den omfatter G13/J13 og eldre ungdomslag samt seniorlag. Sommer-/vintertid og
  forsinkede kjøringer beholder riktig uke. WP-Cron må kjøres av nettstedets scheduler.
- Oppsettet gjenbruker private `rr_fd_cid`/`rr_fd_cwd`-innstillinger. Eventuelle
  RRFR_FOTBALLDATA-konstanter eller miljøvariabler har forrang. Ingen nøkkel kopieres
  til repository eller releasepakken.
- Referatene bruker dagens skriveprofil, redaksjonelle eksempler, separat faktakontroll,
  språkvask og ny faktakontroll ved omskriving. Ukesartikkelens introduksjon gjennomgår
  samme kontroll; alle kamprader gjengis deterministisk fra validerte fakta, som
  eksisterende lagoppstillingsblokker. Hele den lagrede teksten bindes til kontrollen.
- Begge artikkeltypene vises i dagens godkjenningsinnboks/Studio med standardbilde og
  AI-merking. Manuell sluttgodkjenning kreves. Nye rådata kan ugyldiggjøre en kontroll,
  men omskriver aldri redaktørens tekst. Reserverte utkast hindrer duplikater og
  automatiske betalte gjentakelser etter avbrutt skriving.

## Avgrenset release

`scripts/pr30-release.json` binder 32 forventede før-hasher og 35 etter-hasher til
produksjonsbelegget. Bare åtte filer skrives: fem eksisterende og tre nye.
Studio-, theme- og widgetfiler inngår ikke. Før-hasher kontrolleres på serveren før
noen kode endres. API-tilgang, administrator og eksisterende AI-oppsett kontrolleres.
Backup ligger privat utenfor webroten. Kodeinstallasjon bruker atomiske filbytter;
tilbakeføring berører bare release-eide filer, klubbvalg og klubbens cron-hendelser.
En etterkontroll bekrefter alle runtime-hasher, eksisterende innhold/seniorjobb,
Studio-lesing, publiseringssperren og nye kjøringstidspunkter.

Historisk 0.10.4-manifest og produksjonsbelegg endres ikke. CI kontrollerer dette i
et uttrekk fra den fastlåste baselinecommitten, deretter kandidatens egne hasher og
alle isolerte PHP-suiter. Installer-testen dekker kodeavvik, feil pakke, installasjon
og eierskapskontrollert tilbakeføring. Testene skriver ingen ekte artikler og sender
ingen e-post. En grønn kodekontroll er ikke alene bevis på vellykket utrulling;
serverens etterkontroll og direkte readback må bekrefte det.
