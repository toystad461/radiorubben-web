# Dagens Bremnesing – innlogging og stemmekvittering før 2027

Kontroll: 6. november 2026. Arbeidsområde: Plattform / System & Kvalitet,
Lokalt Engasjement og IT & Utstyr. Status: **utviklingskandidat, ikke utrullet**.
Produksjonen og historiske stemmer er ikke endret i dette oppdraget.

## Behov og avgrensning

Publikum skal finne riktig kamp, logge inn én gang, komme tilbake til stemmeskjemaet
og få en pålitelig kvittering. Arbeidet viderefører Site Functions i
`toystad461/radiorubben-web`, uten nytt avstemmingssystem eller ny database.

Thomas' kontrollgrunnlag 6. november: 11 svar; innlogging 3,55/6, stemmegivning
5,00/6. Én person er representert ved to kamper. Kampkoblingen viser registrerte
stemmer, ikke hvor et innloggingsproblem oppstod. Materialet dokumenterer verken
årsaken til en teknisk feil eller effekten av bestemte oppdateringer. Undersøkelsen
er ikke endret, og deltakerne er ikke kontaktet.

## Faktisk miljø og kodeavstemming

Read-only kontroll av WordPress og offentlige sider 6. november:

| Del | Kontrollert resultat | Begrensning |
| --- | --- | --- |
| WordPress | 7.1.4, PHP 8.2.35, Radio Rubben Next 2.0.0-rc.5, Site Functions 1.0.0-rc.4 | Pluginversjonen identifiserer ikke alle manuelle filendringer |
| Vipps | Login with Vipps 1.5.6; fortsett-side ID 313 | Ingen ekte innlogging utført |
| Nettadresse | `home` og `siteurl` er `https://www.radiorubben.no` | `/dagenskamp/` svarer også på domenet uten www, uten videresending |
| Mellomlagring | Kontrollerte anonyme sider/API hadde private/no-store/no-cache og Age 0 | Beviser ikke alle nettleser-, cookie- eller CDN-forløp |
| Mindre kildefiler | `bremnes-direkte-test.php`, `bremnes-poll-performance.php`, `bremnes-poll-rules.php` og `bremnes-poll-match.php` lest fra aktiv plugin; samsvarer med main etter normalisering av avsluttende linjeskift | Nummerert verktøyutskrift er ikke en byteeksakt serverhash |
| Stor hovedfil | Aktiv `bremnes-poll-test.php` er omtrent 101,2 KiB | WPVibe 1.20.3 avviser filen over 64 KiB, også ved linjeavgrensning; full fil og serverhash mangler |
| API | Produksjonen returnerer `authenticated`, `account_name`, `login_nonce`, `logout_nonce` | Feltene finnes i PR #62/#67, men mangler på main; hele filen er ikke dermed verifisert |

Arbeidsbase: main `559a65c8e85004b7365af4f2a7d2fa5269172e23`.
Eksisterende avstemmingsarbeid er gjenfunnet i [PR #67](https://github.com/toystad461/radiorubben-web/pull/67),
head `210461622b790980fb57d34d3f12b53212bb3a51`, og den tidligere PR #62-kjeden.
Poll-filen i `5114c3caa1b3cf96940093a709ddc2fc798a30bb` inneholder dynamisk
innloggingsstatus/noncer, nettleser-retur, tidsgrenser, forespørsler på sidens domene,
embed-støtte og tidligere historiske unntak. Disse er videreført i kandidaten.
PR #67 er ikke merget eller lukket. Resten av den gamle PR-kjeden er ikke importert.

**Kandidaten må ikke erstatte hele produksjonsfilen før den manglende
byteeksakte sammenligningen er utført.** Main alene er ikke en trygg deploykilde.

Aktive Code Snippets er en del av brukerreisen:

- #7, «Radio Rubben kort-URL /kamp», sist registrert endret 20.09.2026:
  `/kamp` sender til dagens valgte kamp på `/dagenskamp/`, uten å videreføre
  query-parametere. Det er en dynamisk kortlenke, ikke en permanent kamplenke.
- #8, «Dagens kamp – testoppsett live 2026», sist registrert endret 29.09.2026:
  faktisk aktiv på side 910. Flytter kort, legger til tabell/vær/2026-statistikk
  og skjuler deler av stemmekortet før start med CSS. Hele koden er lest.
  Denne må være med i den visuelle generalprøven; en isolert pluginvisning er
  ikke nok. Årstallet og forhåndsvisningen må vurderes før 2027.
- #9, dashboard_test-prototype, er separat. Ikke endret.

Snippet-tidene er databaseverdier; tidssonen deres er ikke bekreftet.

## Historikk før Askøy 18. oktober og Fitjar 23. oktober

Tidene under er Europe/Oslo (UTC+2 disse datoene).

| Hendelse | Utviklet/merget | Installert | Kontrollert etter installasjon |
| --- | --- | --- | --- |
| Førstegangsbrukerens Vipps-kobling og treg kampvisning, PR #76 | Merget 10.10 kl. 01:29, merge `cf9ea4b4b4343ed765b2a10bb5e156c511bd1e2f` | [Run 38005979872](https://github.com/toystad461/radiorubben-web/actions/runs/38005979872), 10.10 kl. 01:45:56; kilde `eab6d3a680bc1d277065b5b0112bbaef677efa83`; logg: installed and hash verified | Side, anonymt API og forside kl. 01:46:02. Ikke ekte Vipps/mobil |
| Fotballdata-import, PR #84 | Kilde `e7f6040c017e35de5447baa9818312c44930c743` | [Run 38032672354](https://github.com/toystad461/radiorubben-web/actions/runs/38032672354), 10.10 kl. 08:56:50; installed and hash verified | Offentlig kampvisning, anonymt API og forside kl. 08:56:54–58 |
| Separat kampsimulering, PR #90 | Kilde `b40e5fd3e9612b6f31c02bf6cd603641f266b3a7` | [Run 38039789227](https://github.com/toystad461/radiorubben-web/actions/runs/38039789227), 10.10 kl. 10:59 | Simulering, ikke publikumets Vipps-flyt |
| Oppdateringer Thomas husker ved de to siste kampene | Ikke entydig identifisert i gjennomgått Git-historikk og Actions-kjøringer | **Uavklart** | **Uavklart** |

De dokumenterte installasjonene 10. oktober var før begge kampene. Gjennomgått
historikk ga ikke belegg for en bestemt ny innloggingsversjon installert
18. eller 23. oktober. Dette utelukker ikke manuelle endringer eller andre
utrullingsveier. Endelige før/etter-versjoner krever daterte serverbackuper eller
installasjonskvitteringer. Undersøkelsen brukes ikke som bevis på at en oppdatering
virket eller feilet.

## Brukerreise og bekreftede kodefunn

1. `/kamp` velger gjeldende kamp. Permanent lenke er
   `/dagenskamp/?rr_match=<kamp-ID>`.
2. `bremnes-direkte-test.php` ruter publikum, dashboard og Vipps-retur.
3. `bremnes-poll-match.php` velger kamp; poll-filen viser tilgjengelige kandidater.
4. API-et kontrollerer faktisk WordPress-innlogging og Vipps-pluginens kontokobling.
   Stemmetoken er bundet til velger, kamp, avstemmingssesjon og WordPress-sesjon.
5. Vipps-pluginen eier OAuth-state, cookie, app/nettleser og selve innloggingen.
   Radio Rubben overstyrer retur bare for sine kjente kamp-/dashboard-adresser.
6. Stemmen lagres i eksisterende match-/sesjonsnøkkel i `wp_options`. Entrant-ID
   brukes av eksisterende trekning. Ingen migrering av historiske stemmer.

| Funn | Årsak/belegg | Endring i kandidaten |
| --- | --- | --- |
| Gjentatt registrering gir 409 selv om første stemme finnes | Kontrollert i main og gjenfunnet poll-kode | Bekrefter eksisterende stemme med 200/`voted:true`, også etter stenging. Første valg beholdes |
| Første stemme kan overskrives ved samtidige cache-miss | WordPress `add_option()` bruker SQL-upsert som oppdaterer verdien ved nøkkelkollisjon | Eksisterende unike nøkkel beholdes; INSERT lar kollisjon være en no-op. Direkte tilbakesjekk og cache-invalidering |
| Kvittering kan forsvinne ved neste nettverksfeil eller stenging | Eksisterende render prioriterer frakoblet/stengt før `voted` | Bekreftet kvittering beholdes for samme kamp/sesjon/bruker; nytt GET avklarer mistet POST-svar uten automatisk ny POST |
| Retur har kamp-ID, men lander ikke direkte ved skjema | Lest aktiv returfunksjon | Bevarer kamp og går til `#poll-live` |
| Ny Vipps-runde kan startes fra et gammelt skjema selv om brukeren nå er innlogget | Eksisterende POST-inngang mangler snarvei | Gyldig innlogget bruker sendes rett tilbake til skjemaet |
| Avbrutt login har ingen særskilt kamp-recovery i Radio Rubben-hooken | Lest aktiv inngangsfil og Vipps 1.5.6-hook | Ved bevart betrodd Vipps-sesjon: samme kamp med tydelig prøv-igjen-melding. Manglende sesjon overlates til pluginens eksisterende feilhåndtering |
| To vertsnavn svarer på samme stemmeside | Verifisert offentlig GET | Begrenset kanonisk videresending av publikumsiden til konfigurert www-adresse, uten å endre DNS eller øvrige sider |

Dette er dokumenterte svakheter i tilgjengelig kildekode og observerte
inngangssider, **ikke en reproduksjon av respondentenes problemer i produksjon**.
Følgende er fortsatt hypoteser: cookie-tap mellom sosial nettleser/Vipps/Safari,
feil i førstegangsregistrering, foreldet mellomlagring for bestemte brukere,
appretur til feil nettleser og domeneeffekt på faktisk innlogging.
Timeout og gjenkontroll av innlogging fantes allerede i den gjenfunne PR-koden;
de fremstilles ikke som nye feilrettinger av bekreftet manglende live-funksjon.

## Regler som beholdes

| Regel | Gjenfunnet gjeldende kode |
| --- | --- |
| Kandidater | Startoppstilling ved åpning; innbyttere åpnes når de markeres inn. Tidligere innbyttede forblir valgbare |
| Åpning | Speaker starter første omgang; oppstilling må være klar |
| Stenging | 85:00 aktiv spilletid; pause teller ikke. Manuell stenging bevares |
| Kåring | Eksisterende kode lager kåring ved 86:00 |
| Trekning | Blant alle registrerte stemmer, ikke bare stemmer på spilleren med flest stemmer |
| Visning | Resultat/kåring i administratorvisning; offentlig arkiv etter avsluttet kamp |
| Administrator | Eksisterende unntak lar innlogget administrator stemme uten Vipps-kobling |
| Historiske unntak | Tidligere spesialtrekning for én kamp videreført uendret |

Eldre ønsker om stenging/trekning rundt 80 minutter og trekning bare blant dem
som stemte på vinnerspilleren strider mot gjenfunnet kode. **Ingen av disse
reglene er endret her.** Thomas må velge eventuelle regelendringer som egen
avklaring før sesongen. Denne kandidaten er ikke grunnlag for å endre vilkårene.

## Endret filsett

- `theme-next/rr-site-functions/inc/bremnes-direkte-test.php`: publikumsadresse,
  presis retur, diagnostikk og avbrutt innlogging.
- `theme-next/rr-site-functions/inc/bremnes-poll-match.php`: tar med gyldig
  tilfeldig forløpsreferanse i kampens lenker.
- `theme-next/rr-site-functions/inc/bremnes-poll-test.php`: avstemt gammel
  PR-funksjonalitet, kvittering, dublettvern, matchkontroll, tilgjengelige statuser,
  og tydeligere kampnavn/handling. Beholder 10 sekunders publikumskontroll og
  5 sekunder for dashboard, samt eksisterende 12/30-sekunders GET/POST-grenser.
- `theme-next/rr-site-functions/inc/bremnes-poll-journey.php`: små fellesfunksjoner
  for retur, skrivevern og personvernbegrenset diagnostikk.
- Tester og `.github/workflows/poll-journey.yml`: egen PR-kontroll med isolert MySQL.
  Eksisterende HTTP-test er tilpasset positiv kvittering ved gjentatt innsending.

Ny diagnostikk er av som standard. `RR_POLL_DIAGNOSTICS=true` aktiverer seks
avgrensede hendelsestyper i vertens eksisterende private PHP-logg. Bare type,
kamp-ID, kodefingeravtrykk og en tilfeldig 32-tegns forløpsreferanse logges.
Ingen Vipps-subjekt, navn, e-post, telefonnummer, stemmevalg, cookies,
OAuth-state eller tokens sendes til denne loggen. Under generalprøven brukes
avgrenset loggtilgang og kort oppbevaring, eksempelvis 48 timer.
Kodefingeravtrykket må kobles til kildecommit og filmanifest i installasjonskvitteringen.
Den eksisterende Vipps-pluginens interne logging er ikke endret eller fullstendig revidert.

## Tester og begrensninger

Alle automatiske tester bruker syntetiske identiteter og data. Ingen ekte
Vipps-innlogging, stemme, e-post eller produksjonsdatabase er brukt som test.
Lokalt brukes PHP-WASM; SQLite-kontrollen oversetter konfliktleddet. CI kjører
samme innsettingskode uendret på isolert MySQL 8 og gjenskaper gammel upsert-feil.

| Testgruppe | Dekning | Status før PR-kontroll |
| --- | --- | --- |
| `poll-journey.php` | 24 lokale kontroller av trygg retur, avbrudd, match/sesjon, første stemme og databasefeil; én ekstra MySQL-kontroll | Lokal kontroll kjørt; native MySQL avventes i CI |
| `poll-journey-handler.php` | 17 forløp gjennom faktisk PHP-handler med syntetiske WP-stubber: vanlig/duplikat, stengt, 85-minutt, reserve/innbytter, ugyldig token, annen kamp, databasefeil, API og login-inngang | Lokal kontroll kjørt |
| `poll-journey.cjs` | 13 kontroller av faktisk klientkode i Node: tregt/mistet svar, dobbelttrykk, kvittering, feil kamp, sesjonsbytte og login-knapp/noncer | Lokal kontroll kjørt; ikke en ekte nettleser |
| Eksisterende regresjon | Performance, Fotballdata, kampsimulering og Next-kontroll | Resultat i PR/CI |
| Full WordPress HTTP-integrasjon | Eksisterende `migration-http.py` oppdatert | **Ikke kjørt** i isolert WordPress med denne kandidaten |
| Ny/gjentakende Vipps-bruker | Faktisk brukeropprettelse, kontokobling og retur | **Utestet** |
| iPhone/Safari og Android/Chrome | Faktisk mobil, berøringsflater, kontrast, vertikal plass og appbytte | **Utestet** |
| Sosiale nettlesere | Facebook/Instagram-lenke, Vipps-app og retur til opprinnelig nettleser | **Utestet** |
| Stenging under ekte innlogging | Samme kamp, beskjed om stengt og ingen ny stemme | **Utestet ende til ende**; stengt PHP-handler dekket |
| Oppdatering, treg mobilforbindelse, avbrutt login og ny kamp | Full WordPress/cookie/cache-flyt | **Utestet ende til ende**; komponenttilstander dekket |

Grønn CI er ikke bevis for installert versjon eller hele brukerreisen.

## Kontrollert utrullingsplan – ikke utført

1. Hent byteeksakt kopi og SHA-256 av de fire berørte aktive pluginfilene,
   samt regler/performance, fra WordPress-serveren. Hent aktiv snippet #7/#8
   og konfigurasjon uten hemmeligheter. En lesekanal som støtter den store filen
   er nødvendig; dagens WPVibe-filgrense stopper full sammenligning.
2. Avstem diff mot kandidaten og eksisterende PR #62/#67/#76/#84. Bevar alle
   uavhengige live-endringer. Lag eksplisitt før-/ettermanifest og bind det til
   eksakt PR-head. Stopp hvis en forventet førhash ikke stemmer.
3. Bruk isolert WordPress-testmiljø med samme tema, plugins og snippetvisning,
   syntetiske kamper og Vipps-testoppsett. Ikke klon velgerdata. Kjør HTTP- og
   mobilmatrisen under, inklusive første bruker. Ingen kobling til ekte kamp.
4. Før godkjent produksjonsinstallasjon: ta privat backup av kun berørte filer
   og konfigurasjon, noter om den nye hjelpefilen eksisterte fra før, og prøv
   tilbakeføring i testmiljø. Kontroller grønn CI på eksakt commit.
5. Installer selektivt på WordPress-serveren (ikke Studio-VPS). Legg hjelpefilen
   på plass før filer som krever den, bruk midlertidige filer/atomisk rename og
   hold det korte filbyttet utenfor kamp. Ikke last opp hele main eller PR #67.
6. Kontroller PHP, aktive filhasher, API-ets `journey_version`, friske anonyme
   og innloggede sesjoner, kamp-ID, www-kortlenke, callback og kvittering.
   Invalider eventuell OPcache for endrede filer og relevant sidecache.
   Dokumenter faktisk installasjonstid i Oslo og UTC.
7. Ved feil: gjenopprett de sikkerhetskopierte kildefilene, invalider relevant
   cache og kontroller de tidligere hashene. Fjern ny hjelpefil bare hvis den
   ble opprettet av denne installasjonen, er uendret og ingen gjenopprettet fil
   refererer til den. **Ikke tilbakefør databasen**: nye og historiske stemmer,
   deltakerkoblinger, kamper og trekninger skal bevares.

Eksisterende `deploy-poll.yml` er bundet til en eldre Fotballdata-patch.
Den er ikke oppdatert eller kjørt for denne kandidaten. Den skal ikke brukes
som om den automatisk installerer endringene beskrevet her.

## Kort generalprøve før første kamp i 2027

- [ ] Registrer installert commit, filhasher, kodefingeravtrykk og testkamp-ID.
- [ ] Kontroller kampnavn/dato, permanent kamplenke og dynamisk `/kamp`-lenke.
- [ ] Prøv ny, tilbakevendende, utlogget og allerede innlogget testbruker på
  fysisk iPhone/Safari og Android/Chrome.
- [ ] Åpne lenken både i vanlig nettleser og fra sosiale medier. Godkjenn i
  Vipps-appen og kontroller retur direkte til samme avstemming.
- [ ] Avbryt innlogging, prøv igjen, gå tilbake og oppdater siden.
- [ ] Begrens forbindelsen, dobbelttrykk og mist svaret etter innsending.
  Kontroller én lagret stemme, opprinnelig spiller og tydelig kvittering.
- [ ] Kontroller før åpning, under avstemming, allerede stemt, stengt og
  stenging mens brukeren er inne i Vipps. Ingen ny stemme etter stenging.
- [ ] Bytt testkamp og testkonto: gammel kamp og kvittering må ikke følge med.
- [ ] Kontroller innbyttere, kåring, trekning og skjult offentlig vinner mot
  avtalte regler, samt aktiv snippet #8 på mobil.
- [ ] Gjennomfør rollback-prøve og kontroller at nye teststemmer beholdes.

Ferdig først når den manglende kildeavstemmingen, isolert integrasjon,
mobil/Vipps-matrisen, godkjent installasjon og etterkontrollen er dokumentert.

## Neste evaluering – forslag

La et fremtidig skjema velge konkret kamp og spørre separat om å finne
avstemmingen, logge inn, komme tilbake fra Vipps og avgi stemmen. Dette er kun
et forslag; dagens undersøkelse og samtykker er uendret.

## Tekniske primærkilder

- [Vipps Login browser flow](https://developer.vippsmobilepay.com/docs/APIs/login-api/api-guide/browser-flow-integration/)
- [Login with Vipps-kilde](https://github.com/vippsas/vipps-login-wordpress) – hookene også kontrollert mot publisert plugin 1.5.6.
- [WordPress add_option](https://developer.wordpress.org/reference/functions/add_option/) – databasekonflikt oppdaterer eksisterende verdi.
- [WordPress wp_get_session_token](https://developer.wordpress.org/reference/functions/wp_get_session_token/)
