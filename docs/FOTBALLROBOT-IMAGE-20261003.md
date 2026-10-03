# Fotballrobot 0.9.4 – felles Bremnes/Radio Rubben-bilde

Thomas ba 3. oktober 2026 om å bruke bildet med både Bremnes IL- og Radio Rubben-logoen i den siste kampartikkelen og som standard i fotballroboten.

Verifisert eksisterende WordPress-medie-ID er 812, «Bremnes og Radio Rubben – sportsillustrasjon», 1672 × 941 piksler. Ingen fil er lastet opp på nytt. Den tidligere standarden var 773.

## Gjennomført

- Post 1073 (`rubben-kamp-8984418`) har featured_media=812 og status publish. Tittel, brødtekst og utdrag er identiske med før bildebyttet.
- Mediets tidligere tomme caption-felt er satt til «Illustrasjon. Viser ikke den aktuelle kampen.»
- Den offentlige artikkelsiden viser korrekt bilde og bildetekst. Responsive srcset og object-fit:contain bevarer motivet; Open Graph og Twitter bruker samme bilde.
- Writer::DEFAULT_FEATURED_MEDIA er 812 for nye AI-kampomtaler, både herrer og damer. Opprettelsesflytens eksisterende kontroll mot tidligere utkast og innlegg beholdes; manuelt valgte bilder overskrives ikke.
- Den aktive kampoppgavens gamle bildehenvisning er oppdatert fra 773 til 812. Øvrige instrukser, tidsplan og manuell sluttgodkjenning beholdes.

## Kode og drift

[PR #40](https://github.com/toystad461/radiorubben-web/pull/40) bygger separat på #39. Aktivert kode: `7c122e94ebec102a380377d115de398529bcd38d`.

[Test og selektiv utrulling 37142766989](https://github.com/toystad461/radiorubben-web/actions/runs/37142766989) er bekreftet vellykket, og separat integrasjonskontroll 37142769338 er grønn. Eksisterende testsuite ble kjørt før utrulling. Installert standardverdi og bildets mediedata ble kontrollert i WordPress etterpå.

Bare includes/writer.php, includes/robot.php og pluginens hovedfil ble installert. Hele eksisterende PHP-baseline ble kontrollert mot hasher. Kandidatfiler ble kontrollert før og etter installasjon, med privat sikkerhetskopi og automatisk rollback ved feil.

Backup: `~/.radiorubben-deploy/backups/fotballrobot-image-7c122e94ebec102a380377d115de398529bcd38d/code.tar.gz`.

Autentisert REST bekrefter version=0.9.4, rules_version=1.0.0, delay_seconds=3600 og enabled=true. Ingen main-merge eller bred utrulling. Artikkelens bilde ble endret separat gjennom WordPress REST; kodeutrullingen endret ingen artikler.
