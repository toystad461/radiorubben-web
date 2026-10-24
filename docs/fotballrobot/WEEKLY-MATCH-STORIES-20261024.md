# Ukesresultater og korte kampartikler – klargjøring 24.10.2026

Status: testkandidat. Ingen produksjonsutrulling, artikkelgenerering eller publisering er utført i denne leveransen.

## Leser og redaktør

- Ett felles kampkort og stilark for resultatoversikt, korte kampartikler og redaksjonens forhåndsvisning. Resultat mellom lagene, god plass til lange lagnavn og kilde som egen lenke.
- Ukeskort får en tilgjengelig lenke over kortflaten når den tilsvarende artikkelen er publisert, uten passord/testmerking og med samme kampidentitet, avspark og resultat. NFF-lenken er fortsatt separat. Utkast, private, planlagte og slettede innlegg eksponeres ikke.
- Ukesartikkelens redaksjonsvisning lister kampene. Eksisterende artikler åpnes uendret. Manglende korte referater klargjøres uttrykkelig ett om gangen med eksisterende kildeadapter, skrive-/faktakontroll og språkvask. Normal lengde 60–120 ord; kortere når kildegrunnlaget er tynt. Ingen påtvunget fylltekst, oppdiktede hendelser eller sitater.
- Nye referater har synlig KI-merking, kampkort og deretter ingress/referat. Ingen full lagoppstilling legges automatisk til disse korte artiklene. Kildehenvisninger beholdes.
- Kampoppfølgingen viser mørke kort med status, konkret kontrollfunn når tilgjengelig, Åpne utkast og sammenleggbare tekniske detaljer. Status leses fra dagens innlegg, ikke bare en eldre jobbstatus.
- Knappen «Bruk fast mal på dette utkastet» oppdaterer bare gjenkjente kampkort i et resultat-utkast. Bevarer tittel, prosa, hovedbilde, redaksjonelle beslutninger og verifiserte eksisterende logopar. Nytt innhold gir ny hash og krever ny kontroll. Publiserte innlegg avvises og må bruke eksisterende separat godkjent rettelsesflyt.

## Sperrer og avgrensning

POST og eksisterende REST-autentisering; både redaksjonell tilgang, publiseringsrettighet og tilgang til foreldreinnlegget kreves for klargjøring. Foreldretekst og faktasnapshot inngår i tokenet. Kampen må høre til en avsluttet resultatuke og ha bekreftet sluttresultat. Fersk feed og NFF-detaljer må stemme. Endring under modellarbeid stopper lagring av den nye teksten. Eksisterende artikler og uavklarte skrivejobber gjenbrukes/avklares, ikke overskrives eller blindt kjøres på nytt. Låser deles med seniorkjøringen; ungdomskjøringen beholder eksisterende reservasjonslås.

Alle nye artikler er utkast. En grønn modellkontroll er ikke en menneskelig godkjenning. Eksisterende PublicationGate og ReviewDesk er beholdt. Ingen bulkpublisering, e-post, ny scheduler, endret TTS-flyt eller endring i Studio.

Logoer må allerede finnes i kildegrunnlaget/konfigurasjonen eller i det kontrollerte ukeskortet. Det gjettes ikke klubblogo ut fra lagnavn. Manglende logo gir fortsatt et lesbart tekstkort. Publisering/tilbaketrekking av en kampartikkel tømmer tilgjengelig WP-Optimize-sidecache slik at kortlenker oppdateres. Andre cacheleverandører må kontrolleres ved installasjon.

Eksisterende publiserte referater får ikke omskrevet tekst eller nytt lagret kampkort automatisk. Den nye artikkelmalen gjelder nyopprettede korte referater. Oppdatering av tidligere publiserte referater krever egen rettelsesvurdering.

## Grunnlag og overlapp

Egen gren fra Web #104, a9e11eab643e81557948c1a12531f714e6b9b0e1, som er dokumentert selektivt utrullet via #105. Main er fortsatt 5169765 og inneholder ikke dette komplette produksjonsgrunnlaget. Ikke installer hele main eller hele feature-grenen.

Fersk GitHub-kontroll: #106 endrer ekstern scheduler og er ikke utrullet; #107s separate én-fils temarettelse for deling er dokumentert utrullet. Ingen av disse filene berøres. Studio #110 er allerede del av den tidligere samordnede leveransen. Historiske releasekvitteringer endres ikke.

## Kontroll og selektiv installasjon etter egen godkjenning

1. Kontroller eksakt kandidatcommit og grønn CI. Manifestet ved siden av dette dokumentet angir de åtte runtime-filene og før-/etter-SHA-256. Ny fil har ingen før-hash.
2. Les ferske produksjonsbytes for alle åtte. Krev før-hash lik dokumentert base og at den nye filen ikke allerede finnes. Stopp ved avvik; ikke overskriv nyere arbeid. Ta også redaksjonelt før-fingeravtrykk, inkludert utkast 1467, bilde 812 og godkjenningsmetadata.
3. Bruk eksisterende autoriserte SSH/rsync-mekanisme, privat staging, deploy-/schedulerlåser og privat filbackup med moduser og manifest. Backup av en ny fils fravær må registreres. Ikke bruk en gammel automatisk release-workflow for denne nye leveransen.
4. Installer bare manifestets åtte filer atomisk under kort vedlikehold. Bevar database, media og redaksjonelle metadata. Last PHP, kontroller ruter/tilganger og tøm aktuell sidecache. Etter-hasher skal stemme før vedlikehold avsluttes.
5. Kontroller mobil 320/390 og desktop 1280, redaksjonens faktiske sanitiserte forhåndsvisning, hovedtema, lange lagnavn, begge logoer, tabulator/fokus, NFF-lenke og kampartikkellenke. Bekreft at skjulte innlegg aldri lenkes.
6. Utkast 1467 migreres først gjennom den eksplisitte knappen, etter kontroll av lagrede 14 kamper og 28 logoer. Denne innholdsendringen er separat fra kodeinstallasjonen. Kontroller lagret tekst på nytt i eksisterende flyt. Klargjør ett manglende referat som pilot; kontroller språk, fakta og layout før flere.
7. Tilbakeføring: verifiser at installerte bytes fortsatt er kandidatens, gjenopprett bare backupens sju eksisterende filer og fjern bare den hashverifiserte nye match-stories.php. Tøm cache. Ingen database-restore som kan slette nyere artikler eller beslutninger. Utkast som er opprettet beholdes; ingen publisering følger rollback.

## Validering

Lokalt: PHP-WASM 8.5.10, syntakskontroll av 69 PHP-filer og 30 isolerte suiter. Ny suite dekker tillatelser, gammel nettlesertoken, endret foreldrekilde/tekst, ferskt resultatavvik, avlysning, låser, eksisterende uavklart jobb, endring under skriving, duplikater, utkaststatus, offentlig synlighet, kildelenker og draft-only malbytte. Eksisterende publiserings- og endrede-kilder-tester kjøres uendret.

CI er utvidet med syntetiske visningsprøver ved 320, 390 og 1280 piksler og skjermbilder som artifact. CI-skjermbilder er ikke bevis på faktisk WordPress-produksjon eller Safari. Ingen reelle modellkall eller produksjonsdata brukes i testene. Produksjonskontroll og redaksjonell vurdering av en ekte kortartikkel gjenstår.


## Neste steg: virkelig WordPress og navigasjon (24.10.2026)

Lesende kontroll bekrefter produksjon WordPress 7.1.3, aktiv hovedmeny 58 (primary), uten Sport. Sport-siden er 769, publisert med standard sidemal og sist endret 04.10.2026 14:29:14 UTC. Siden har en manuelt lagret oversikt for uke 38, ikke en dynamisk lenke til ukesresultater. Kun produksjonen er tilkoblet WPVibe; det er ikke bekreftet en tilgjengelig separat WordPress-testinstans der.

Ny shortcode `[rrfr_weekly_results]` gir en fast seksjon «Ukesresultater» med de tolv nyeste publiserte ukesoppsummeringene. Private, passordbeskyttede, testmerkede og upubliserte innlegg vises ikke. Ingen publiserte resultater gir en ærlig tomtilstand. Leservei: Forside → Sport → Ukesresultater → ukesoppsummering → publisert kampreferat.

Den konkrete produksjonsendringen er klargjort i SPORT-NAVIGATION-20261024.json, med hash av dagens side og den utdaterte, avgrensede ukesblokken. Ved separat godkjent aktivering: ta revisjons-/menybackup, krev samme før-hash, erstatt bare blokken mellom de oppgitte markørene med shortcoden, endre snarveien til «Ukesresultater», og legg Sport (side 769) i meny 58 bare dersom den ikke allerede finnes. Bevar øvrige menypunkter, toppscorere, spillerkamper og artikler. Ikke publiser en ukeartikkel for å fylle tomtilstanden. Tilbakeføring gjelder bare denne siderevisjonen og et eventuelt nyopprettet menypunkt, med kontroll mot senere endringer.

CI utvides med en disponibel, ekte WordPress-installasjon og MySQL. Kandidatpluginen aktiveres der. Testen bruker virkelige WordPress-roller, REST, KSES, post-/metahåndtering, kortkodetolkning, menyer og eksisterende publiseringssperrer, men bare syntetiske innlegg og kontrollmetadata. Nettverk og e-post blokkeres under selve integrasjonstesten; ingen reelle AI-kall. Produksjonspublisering, produksjonsmenyer og produksjonssiden endres ikke av denne testen. Faktisk produksjonsnettleser/Safari gjenstår etter separat godkjent installasjon.
