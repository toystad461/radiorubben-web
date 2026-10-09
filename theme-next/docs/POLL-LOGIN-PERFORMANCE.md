# Dagens kamp: første Vipps-innlogging og ventetid

Undersøkt 10. oktober 2026. Produksjon bruker Radio Rubben Next og rr-site-functions.

Login with Vipps 1.5.6 sin offentlige kildekode kaller `map_phone_to_user`
med `$user` før den nye brukeren hentes med `get_user_by`. Første innlogging
kan derfor gi en WordPress-konto uten Vipps-koblingen avstemningen krever.
Den andre innloggingen finner den eksisterende brukeren og etablerer koblingen.
Den installerte versjonen er bekreftet; WPVibe kan ikke lese den store
VippsLogin.class.php-filen direkte på grunn av grensen på 64 KiB.

Rettelsen bruker pluginens `continue_with_vipps_after_create_wordpress_user`
med brukerobjektet som faktisk ble opprettet, og providerdata fra den verifiserte
VippsSession. Den krever bekreftet e-post, samsvarende brukeradresse, telefon,
sub og en lokal avstemnings-/dashboard-returadresse. Eksisterende koblinger
endres ikke. Ingen brukere eller stemmer migreres.

Kampintroduksjonen hentet opptil tre eksterne sider med 15 sekunders timeout
per forespørsel ved utløpt cache. Den viser nå lagret introduksjon umiddelbart
og planlegger oppdatering via WP-Cron. Siste innhentingstid beholdes ved feil.
Også automatisk kampbytte kjøres i bakgrunnen. WP-Cron må være operativ;
ved manglende kjøring vises eksisterende kamp/data til jobben blir kjørt.
Publikumsstatus oppdateres hvert tiende sekund og pauser i skjult fane;
dashboardet beholder fem sekunder. Serveren validerer fortsatt stemmer og frist.

Direkte offentlige GET-observasjoner før endringen: www-kampside 25,77 sekunder,
www-status-API 22,30 sekunder og kampside uten www 1,14 sekunder. Dette er
enkeltmålinger uten isolering av nettverk og vert; de beviser ikke én årsak.
WPVibe-metadata målte 2,649 sekunder nettverk og 0,020 sekunder PHP.

## Validering

`php theme-next/tests/poll-performance.php` tester første kobling, avvisning av
ugyldig identitet/returadresse, bevaring av eksisterende kobling, kald/varm
cache uten ekstern henting, bakgrunnsjobber uten duplikater, gamle tidsstempler
og kildefeil. `python3 theme-next/scripts/check.py` kontrollerer PHP- og
JavaScript-syntaks samt pakkegrenser. Ingen produksjonsdata skrives av testene.

Før produksjonsaktivering: prøv ny Vipps-bruker fra mobil én gang, kontroller
at spillervalg åpnes uten ny innlogging, send én teststemme i en isolert kamp,
og kontroller at andre stemme avvises. Prøv også eksisterende Vipps-bruker og
at bakgrunnsjobbene oppdaterer kampintroduksjon og kampvalg.

Endringen er en kandidat i funksjonsutvidelsen, ikke et nytt tema. Pakken skal
ikke erstatte nyere serverkode uten å sammenligne filene først.
