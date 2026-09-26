# Radio Rubbens Fotballrobot 0.6.0

Spillerfølging er implementert i denne versjonen. Se [PLAYERS.md](PLAYERS.md) for bruk, arkitektur, tester og begrensninger. 0.5.0 ble installert og Tiril (FIKS 3942773) ble kontrollert mot offentlige data. Se [PLAYER-REVIEW.md](PLAYER-REVIEW.md) for godkjenningsflyten i 0.6.0.

## Historikk fra eksisterende 0.3.2

# Radio Rubbens Fotballrobot 0.3.2

Installert og aktivert 25. september 2026. Arbeidsområde: https://www.radiorubben.no/wp-admin/admin.php?page=rr-fotballrobot

## Bruk

1. Velg NFF-kamp-ID for Bremnes herrer A eller damer A.
2. Hent kampgrunnlag. Eksisterende kamparkiv bekrefter kampslutt; ellers må administrator kontrollere dette.
3. Les resultater, historikk, kilder og varsler. Velg en dokumentert vinkel.
4. Lag eller åpne separat prøveutkast. Knappen lager en regelbasert faktatekst, ikke et ferdig AI-referat. Eksisterende utkast overskrives ikke.

Den eksisterende ChatGPT Work-oppgaven «Publiser Bremnes-kampomtaler» er oppdatert til å hente faktapakken før nye kampomtaler skrives. Tidsplan og publiseringsfullmakter er beholdt. Prøveutkast publiseres ikke automatisk. Første fremtidige planlagte kjøring er ikke testet ende til ende.

## Data og grensesnitt

Autentisert administrator med redigeringstilgang kreves. GET `/rr-fotballrobot/v1/matches/{id}` leser siste grunnlag. POST samme rute med `{}` oppdaterer. `confirmed_finished:true` krever uavhengig kontrollert kampslutt. POST `/rr-fotballrobot/v1/matches/{id}/draft` med `fact_hash` og `angle` lager eller gjenbruker prøveutkast.

NFF-kilder mellomlagres i ti minutter. Historikk avgrenses til samme lag, turnering og sesong før avspark. Uavklarte resultater bryter beregningsgrunnlaget. `wins_exact:false` betyr minst oppgitt antall. Tabellplass beregnes ikke. Endringer i NFFs HTML kan kreve parseroppdatering.

Faktagrunnlag lagres privat i `rr_robot_fact`; utkast lagrer egen faktakopi. Voteridentiteter og premievinner inkluderes ikke. Ingen egen AI-nøkkel, ny tidsplan, dashboardendring eller temautrulling.

## Verifisering

- `php tests/run.php`: 18 kontroller bestått, inkludert duplikater, manglende resultater, ufullstendige mål og selvmål.
- Kamp 8985491: 2–2; Bremnes fire og Viggo fem strake seire før avspark.
- Live faktainnhenting uten varsler; prøveutkast 987 opprettet som draft.
- Gjentatt generering gjenbruker 987; feil faktahash avvises; anonym REST-tilgang avvises.
- Eksisterende publisert artikkel 983 beholdt. Ingen nye artikler publisert.
- Planlagt oppgaves oppdaterte instruks kontrollert etter ny innlasting.

## Tilbakerulling

Deaktiver utvidelsen i WordPress. Lagrede faktagrunnlag og prøveutkast beholdes. Fjern det merkede faktapakketillegget fra den eksisterende artikkeloppgaven dersom integrasjonen skal avsluttes; oppgavens øvrige instrukser beholdes.

## Oppdatering 0.2.0

Installert 25. september 2026: egen Writer-klasse, administrativt AI-oppsett, Responses API med strukturert resultat, separat faktaredaktør-kall og klikkstyrt utkastlagring. Ingen betaling/API-kall er startet i denne leveransen. API-konto, betaling og nøkkel må konfigureres av Thomas. Virkelig API-kjøring og modelltilgang er ikke verifisert.

Nøkkelen kan settes som RRFR_OPENAI_API_KEY på serveren eller legges inn i administrasjonsskjemaet. Skjemaet lagrer AES-256-GCM-kryptert verdi med nøkkel avledet fra WordPress auth-salt, autoload deaktivert. Endring av salt krever ny innlegging. Server/WordPress-administrator må fortsatt regnes som betrodd; kryptering erstatter ikke serverens tilgangskontroll.

POST /matches/{id}/write returnerer eksisterende AI-utkast eller en kortlivet review_token. POST /review med token utfører separat AI-faktakontroll og lagrer bare draft ved godkjenning. Token er bundet til bruker, gyldig i 15 minutter, og nytt faktagrunnlag avviser eldre tekst. Ingen automatisk publisering. AI-vurdering er ikke en garanti for faktariktighet; redaktør må lese. Timeout kan føre til leverandørkostnad uten lagret tekst. Ved hard PHP-avbrytelse blir sikkerhetslåsen stående til administrator undersøker og nullstiller den. Ingen automatisk betalt retry.

33 lokale kontroller fra parser/format/nøkkeltester og ytterligere 15 flytkontroller med simulert leverandør består. Kontroller dekker avvist faktakontroll, klikkduplikater, tilgang til review-token, uferdig kamp, HTTP-feil og lås. Live versjonsstatus, administratorvisning, deaktivert skriveknapp uten nøkkel og avvist gammel hash er kontrollert. Ekte skrive-/kontrollkvalitet gjenstår å teste etter API-oppsett.

Prøveutkast 987 er omskrevet redaksjonelt i samtalen og merket som språkeksempel, ikke som resultat av API-knappen. Publisert artikkel og dashboard er ikke endret.

### Ekte API-prøve etter konfigurering

Etter at Thomas bekreftet ferdig API-oppsett, ble kamp 8985491 skrevet via /write og kontrollert via /review med faktisk leverandør. Begge kall fullførte. AI-utkast 993 ble lagret som draft: «Nesse reddet Bremnes med utligning i sluttminuttene». Tekst lest tilbake og kontrollert mot faktapakken (mål, kort og rekker). Prøven viser fungerende integrasjon, ikke at språkkvaliteten er ferdig evaluert på flere kamper. Prøveutkast 987 og publisert artikkel er beholdt.

## Oppdatering 0.3.2 – kampreferatmal

Egen artikkelmal for innlegg med `_rrfr_ai_match`: mindre overskrift, kompakt AI-merknad, resultatkort med logoer, lesbar ingress/tekst, lagoppstillinger, dommere og kilde. Hendelser bruker samme hjemme/minutt/borte-tidslinje, ramme, farger og symboler som Dagens kamp. NFF er autoritativ for mål/kort; bare gyldige manuelle bytter tas med fra arkivet. Byttetid avrundes opp til kampminutt slik som Dagens kamp. Identiske bytter vises én gang. Voterdata, premievinner og speakernotat utelates.

Utkast 993 beholdes upublisert og teksten er uendret; kategori er Fotball (17). Dashboard, dashboard_test, tema og andre artikkeltyper endres ikke. Desktop- og mobilbredde 390px kontrollert. Automatiske kontroller dekker filtrering av manuelle data, tidsrekkefølge, minuttavrunding og referee-parser.

Skriveinstruks og separat faktakontroll kobler nå manuelt dokumenterte innhopp med senere offisielle mål/kort ved entydig fullt navn, samme lag og riktig tidsrekkefølge. Dette er en vedvarende redaksjonell regel, ikke automatisk modelltrening. Ingen ny betalt tekstgenerering ble kjørt for denne regelen; eksisterende artikkelprosa beholdes.



## Lær av mine rettelser — bevart fra 0.4.1

Ligger i pluginen, uavhengig av WordPress-tema. Administrator med tilgang til artikkelen åpner Fotballrobot → Lær av mine rettelser, velger et lagret AI-referat, beskriver lærdommen og godkjenner tekstkopien. Rediger og lagre i artikkeleditoren først; ulagrete endringer overføres ikke. Et eget felt i artikkeleditoren lenker til læringssiden.

Privat metadata `_rrfr_learning` lagrer original (bare når den faktisk finnes), godkjent tekst, forklaring, bruksområde, innholdshash, versjon, tidspunkt og administrator-ID. Ingen automatisk lagring fra autosave eller publisering. Administrator kan deaktivere eksemplet. Metadata brukes ikke i offentlig mal eller REST-respons. Læring lagres bare med WordPress-nonce, administrator-/artikkelrettigheter, eksplisitt godkjenning og samsvarende artikkel-/læringsversjon.

Ved ny AI-skriving velges inntil tre aktive og uendrede eksempler. Det finnes generelle eksempler og eksempler for dokumentert innbytter som scorer. Relevante spesifikke eksempler prioriteres; deretter nyeste. Teksteksemplene begrenses til tittel og tre avsnitt à 1200 tegn. Kampdata fra eksemplene er ikke kilder for ny kamp. Faktaredaktøren får bare den aktuelle faktapakken, ikke eksemplene. `_rrfr_learning_used` dokumenterer eksemplene som ble brukt; `_rrfr_original_article` bevarer originalen for nye AI-utkast. Eksisterende eldre utkast får ingen oppdiktet original.

Lagring krever ingen nye AI-kall. Teksteksempler følger med den eksisterende AI-forespørselen og gir noe mer input ved senere skriving. Modellen finjusteres ikke. Endelig tekstkvalitet må vurderes på flere reelle kamper.

Verifisering: 21 læringskontroller og 18 skriveflytkontroller med simulert leverandør, i tillegg til tidligere parser-/skriver-/rapportkontroller. Tester dekker feil rettigheter, manglende godkjenning, gamle tekstversjoner, deaktivering, gjenaktivering, navne-/tidskobling, dataminimering og at eksempler ikke sendes til faktaredaktøren.

Tilbakerulling: installer `work/radio-rubben-fotballrobot-0.3.2-backup.zip`; metadata beholdes uten å bli brukt. Ved samtidig utvikling av spillerprofiler må læringsfilen, kroker, Writer-tilkobling og administrasjonslenker flettes før neste fullstendige pluginopplasting.
