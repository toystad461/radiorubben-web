# Radio Rubben: forstå theme og bytt trygt

**En brukerveiledning for Thomas · 26. september 2026**  
Gjelder Radio Rubben Next **2.0.0-rc.1** og det undersøkte grunnlaget for dagens nettside.

## 1. Det viktigste før du begynner

**Du skal kunne erstatte dagens theme, men den nye versjonen skal ikke aktiveres på radiorubben.no ennå.** Vi har laget det nye presentasjonslaget. Funksjonene er nå flyttet til en separat utvidelse og prøvd på en isolert kopi. Ekte Vipps-innlogging, nye eksterne data og endelig driftssjekk gjenstår. Se [oppdatert stagingrapport](docs/MIGRERING-OG-STAGING.md).

Bekymringen din er derfor relevant. Et theme-bytte sletter normalt ikke artikler og bilder, men funksjoner kan slutte å virke dersom koden som driver dem, ligger i themet som blir slått av. Data kan fortsatt finnes selv om siden, knappen eller administrasjonsverktøyet blir borte.

Dette skal løses før byttet. Du skal ikke måtte trykke «Aktiver» og håpe at alt følger med.

**Det du trygt kan gjøre nå:** lese veiledningen, fortsette vanlig redaksjonelt arbeid og lage en oversikt over funksjonene du bruker. Installasjon og utprøving av kandidaten gjøres på en egen testside.

## 2. Slik henger WordPress sammen

| Del | Hva betyr det? | Eksempel hos Radio Rubben |
|---|---|---|
| WordPress | Grunnsystemet som organiserer nettsiden | Innlegg, sider, brukere og kategorier |
| Database | Lagringen av innhold og innstillinger | Artikkeltekster, medlemsopplysninger og lagrede kampdata |
| Mediebibliotek | Bildene og andre opplastede filer, med opplysninger i databasen | Artikkelbilder, logo og portrett |
| Theme / tema | Filene som bestemmer hvordan sidene vises | Toppmeny, forside, artikkeloppsett og kampkort |
| Plugin / utvidelse | En egen pakke med funksjonalitet | Fotballrobot, radiointegrasjon og nyhetsinnhenting |
| Mal | Et bestemt sideoppsett i themet | Artikkel, kategoriarkiv eller sportsside |
| Mønster | Et ferdig oppsett med blokker du kan sette inn i en side | En programomtale eller sponsorflate |
| Child theme / undertema | Et lite tillegg til hovedthemet for egne kodeendringer | En særskilt visuell tilpasning som skal tåle oppdatering av hovedthemet |

Et godt skille er at kampfunksjonen bestemmer **hvem som spiller og hva stillingen er**, mens themet bestemmer **hvordan kampkortet ser ut**. Da kan vi senere endre utseendet uten å bygge kampfunksjonen på nytt.

Dagens Radio Rubben har deler av disse oppgavene samlet i themet. Derfor er byttet mer enn en vanlig designoppdatering. WordPress beskriver den generelle theme-funksjonen i [Work with themes](https://wordpress.org/documentation/article/work-with-themes/).

## 3. «Installert» og «aktivert» er to forskjellige ting

**Installere** betyr å legge theme-filene inn i WordPress. Flere themes kan være installert samtidig.

**Aktivere** betyr å velge hvilket theme nettsiden faktisk bruker. Når du aktiverer et nytt hovedtheme, slutter WordPress å laste det gamle themets funksjonskode. Et aktivt child theme laster sitt eget hovedtheme som forelder.

Å laste opp et nytt theme er altså ikke det samme som å bytte nettsiden over til det. Likevel gjør vi hele utprøvingen på testsiden, slik at det ikke er tvil om hvor vi arbeider.

**Behold det gamle themet. Ikke slett det for å «rydde opp».** Det inngår i planen for å gå tilbake hvis noe feiler.

## 4. Hva kan følge med, og hva kan falle ut?

| Del av nettsiden | Hva betyr et theme-bytte? |
|---|---|
| Vanlige innlegg, sider og kategorier | Ligger normalt fortsatt i databasen. Visningen kan endres. |
| Bilder | Filene skal fortsatt finnes. Utsnitt, størrelser og plassering kan endres. |
| Vanlige nettadresser | Det nye themet endrer ikke adressene. Vi skal likevel teste eksisterende lenker. |
| Menyer, logo og theme-innstillinger | Kan trenge ny tilordning eller kontroll. Det nye themet forsøker å lese flere gamle innstillinger som reserve. |
| Widgets og egen CSS | Flyttes ikke nødvendigvis til riktig plass. Den nye kandidaten har ikke tradisjonelle widgetområder. |
| Kortkoder, for eksempel tekst i hakeparenteser | Krever fortsatt pluginet eller funksjonskoden som tolker dem. Ellers kan koden vises som tekst eller innhold utebli. |
| Egen innholdstype, som RRLive-kamper | Data kan være lagret selv om menypunkt og adresser blir utilgjengelige når registreringskoden mangler. |
| Funksjoner kodet i gammelt theme | Slutter å bli lastet ved theme-bytte. Disse må få et eget, fungerende hjem først. |

At en artikkel fortsatt finnes i administrasjonen, beviser derfor ikke at alle funksjoner på nettsiden virker. WordPress forklarer også at [widgetområder kan endres ved theme-bytte](https://wordpress.org/documentation/article/manage-wordpress-widgets/).

## 5. Dette gjelder konkret for Radio Rubben

Oversikten nedenfor bygger på kodegrunnlaget vi har undersøkt. Den første lokale kopien var fra 23. september. Migreringen er nå basert på en ny kopi av kode og database fra 26. september. En fersk eksport og lokal overgangstest er nå gjennomført; tabellen beskriver avhengighetene som måtte sikres. Gjeldende gjennomføringsstatus står i stagingrapporten.

| Funksjon | Det vi vet | Hva som må være gjort før bytte |
|---|---|---|
| Dagens Bremnesing, dashboard, neste kamp og speaker | Viktige deler ligger i gammelt theme. | Flytt dagens funksjonskode til egen utvidelse. Test kampvalg, stemmer, visning, rettigheter og historikk. |
| RRLive | Gammelt theme registrerer kampinnhold og spesielle adresser. | Bevar registrering og adresser i separat utvidelse. Nye theme har visningsmaler, men starter ikke kampmotoren. |
| Fotballrobot | Finnes som egen utvidelse. Samspill med nytt theme er prøvd lokalt. | Behold utvidelsen. Test reelle artikler, faktakort og byline på testsiden. |
| Min Rubben og Vipps | Funksjoner er fordelt mellom plugins og gammelt theme. | Sikre innlogging, konto, sletting og medlemsvisning. Test med godkjent testoppsett. |
| Quiz | Finnes både som plugin og kode i gammelt theme. | Flytt theme-avhengighetene og test hele quizflyten. |
| Vær | Funksjonskode ligger i gammelt theme. | Flytt innhenting og nødvendig visning til separat utvidelse. |
| Bømlo kommune / RSS | Nyhetsutvidelse finnes; ny forside trenger riktig kobling til den. | Koble til eksisterende nyhetsdata og kontroller kilde og lenker. Ingen ekstra, konkurrerende innhentingsjobb. |
| Radio | Egen radio-utvidelse og innstillinger i themet samarbeider. | Test faktisk lyd, riktig strøm og at det ikke dukker opp to spillere. |

**En pen kampflate er ikke bevis på at avstemningen fungerer.** Tilsvarende er et dashboard-oppsett bare en ramme til den faktiske funksjonen er koblet inn.

## 6. Hva har vi laget nå?

Den nye kandidaten inneholder forside, artikler, vanlige sider, kategoriarkiver, søk, 404-side og visuelle oppsett for sport, radio, RSS, journalister, sponsorer og kamp/live. Den har også rammer som eksisterende applikasjoner kan bruke.

De fire installasjonspakkene har ulike oppgaver:

| Pakke | Bruk |
|---|---|
| `radio-rubben-next-2.0.0-rc.1.zip` | Selve hovedthemet. Installeres under **Utseende → Temaer**. |
| `rr-site-functions-1.0.0-rc.1.zip` | Sikrer funksjonene fra gammelt theme. Installeres under **Utvidelser**, sammen med de eksisterende pluginene. |
| `rr-editorial-contract-1.0.0-rc.1.zip` | Valgfri utvidelse for journalist-ID og kildeopplysninger. Installeres under **Utvidelser**. Erstatter ingen kamp- eller medlemsmotor. |
| `radio-rubben-child-1.0.0.zip` | Valgfritt undertema for egne visuelle kodeendringer. Krever at hovedthemet er installert. |

`rc.1` betyr første utgivelseskandidat: en versjon som skal vurderes og testes før endelig godkjenning. Det betyr ikke «klar til direkte produksjonsbytte».

Koden har bestått lokale kontroller, inkludert 54 integrasjonskontroller. GitHub kontrollerer kode og bygger ZIP-er automatisk. **Dette er ikke en full test av dagens nettside med alle faktiske integrasjoner.** Detaljer finnes i [QA-notatet](radio-rubben-next/docs/QA.md).

## 7. Veien fra dagens nettside til nytt theme

### Trinn 1: Ta vare på det som virker

Vi trenger en fersk backup av både **database og filer**, inkludert themes, plugins, bilder og nødvendig konfigurasjon. Vi må også vite hvordan den gjenopprettes. GitHub-koden alene er ikke en slik backup. Dette følger WordPress sin [beskrivelse av full backup](https://developer.wordpress.org/advanced-administration/security/backup/).

**Ferdig når:** en kopi er gjenopprettet på testmiljøet, og vi vet hvilken dato den representerer.

### Trinn 2: Lag en egen testside

En testside, ofte kalt *staging*, er en separat kopi som besøkende på radiorubben.no ikke bruker. Den trenger egen database og egne filer. Bare en annen nettadresse som peker til samme database er ikke tilstrekkelig.

Testsiden skal være tilgangsbeskyttet. Den skal ikke sende ekte medlemsmeldinger, gjennomføre betalinger, publisere automatisk eller endre pågående kamper. Eventuelle personopplysninger i kopien må håndteres med samme omtanke som på hovedsiden.

**Ferdig når:** vi kan teste uten å påvirke publikum, medlemmer eller aktive kampdata.

### Trinn 3: Gi funksjonene egne utvidelser

Her flyttes nødvendig kode fra gammelt theme til egne funksjonsutvidelser. Lagrede data, nettadresser og rettigheter skal beholdes. Dette er utviklerarbeid; du trenger ikke kopiere kode eller redigere PHP selv.

Gammel og ny kopi av samme motor skal ikke kjøre samtidig. Det kan ellers gi doble jobber eller tekniske feil. Overgangen må derfor planlegges som en samlet kombinasjon av theme og plugins.

**Ferdig når:** funksjonene ikke lenger trenger det gamle themet for å virke.

### Trinn 4: Prøv det nye themet på testsiden

Når funksjonene er sikret, gjøres dette **på testsiden**:

1. Kontroller nettadressen slik at du vet at du ikke er på produksjon.
2. Åpne **Utseende → Temaer → Legg til nytt → Last opp tema**. Ordlyden kan variere litt.
3. Velg ZIP-en for hovedthemet og installer.
4. Aktiver kandidaten som del av den avtalte testovergangen med riktige plugins.
5. Kontroller logo, menyer, forside, kontaktopplysninger og radiostrøm.
6. Gå gjennom prøvelisten nedenfor før noe vurderes som klart.

GitHub-knappen **Download ZIP** gir hele kodeprosjektet. Den ZIP-en skal ikke lastes opp som theme. Bruk den egne installasjonspakken. Hvis du laster ned en samlet GitHub Actions-pakke, pakk den ut først; inni ligger de fire separate ZIP-ene.

### Trinn 5: Godkjenn med konkrete bevis

Du trenger ikke lese koden. Du skal kunne bruke testsiden og kjenne igjen arbeidsoppgavene dine. Feil registreres og rettes før byttet.

**Ferdig når:** prøvelisten er bestått, ingen viktige funksjoner mangler, og tilbakeføring er prøvd.

### Trinn 6: Avtal selve byttet

Byttet legges til et rolig tidspunkt, uten aktiv kamp eller avstemning. Ta ny backup rett før. Avtal hvordan nye artikler, medlemmer og stemmer mellom testkopi og produksjon skal bevares.

**Ikke kopier en gammel testdatabase over produksjon.** Da kan nyere innhold og aktivitet gå tapt. Utvikleren må angi nøyaktig hvilke filer og innstillinger som skal endres.

Etter byttet testes de viktigste funksjonene igjen på produksjon på en kontrollert måte.

## 8. Din prøveliste: gjør vanlige arbeidsoppgaver

Bruk både mobil og datamaskin. Test som utlogget besøkende og med riktige testkontoer der innlogging er nødvendig. «Ikke testet» betyr at punktet fortsatt er åpent.

| Oppgave | Hva du skal se etter | Resultat / merknad |
|---|---|---|
| Åpne forsiden | Riktig logo, bilder, rekkefølge og fungerende mobilmeny | |
| Åpne en gammel artikkellenke | Samme artikkel og adresse, riktig bilde og lesbar tekst | |
| Åpne en robotartikkel | Faktakort, innhold og tydelig digital byline | |
| Gå gjennom Sport → Fotball → Bremnes IL → Herrer/Damer | Riktig kategori, saker og videre navigasjon | |
| Åpne Lederens ord | Riktige saker og visning | |
| Søk og bla til side 2 i et arkiv | Reelle treff og fungerende videre lenker | |
| Åpne en RSS-sak | Kilde, tittel og lenke stemmer | |
| Start og stopp radio | Faktisk lyd, ingen dobbel avspilling | |
| Åpne program, kontakt, personvern og sponsorsider | Riktig innhold, lenker og skjema | |
| Bruk Dagens Bremnesing på testkamp | Stemmegivning og resultater oppfører seg riktig | |
| Bytt testkamp i dashboard | Endringen vises riktig på tilknyttede flater | |
| Bruk speaker og RRLive | Riktig kamp, hendelser og tilgang | |
| Logg inn og ut som testmedlem | Konto og tilgang fungerer; andre brukeres data er utilgjengelige | |
| Prøv quiz og vær | Reelt innhold og fungerende handlinger | |
| Prøv tilbakeføring på staging | Kjent fungerende theme-/plugin-kombinasjon kommer tilbake | |

Betaling, kontosletting, skjema-utsending og automatiske jobber testes med et særskilt testoppsett. Ikke bruk en ekte medlemskonto eller aktiv produksjonskamp for slike forsøk.

## 9. Slik arbeider du i det nye themet etter godkjent bytte

### Artikler og kategorier

Du bruker fortsatt **Innlegg** til nyhetssaker og **Sider** til faste sider. Kategoriene beholdes. Et theme-bytte er ingen grunn til å lage nye kategorier eller erstatningssider med nye nettadresser.

### Logo, kontakt og radio

Under **Utseende → Tilpass** finner du vanlige tilpasninger og **Radio Rubben – presentasjon**. Her finnes blant annet kontaktinformasjon, strøm-URL, portrett og forsidelayout. Logo og menyplasseringer kontrolleres i sine respektive paneler.

Feltene for artist, låt og direkte-status er presentasjonsinnstillinger. Å skrive inn «direkte» starter ikke en sending og lager ikke en automatisk programplan.

### Forsiden

Det finnes to valg: **Dagens Radio Rubben-layout** og **Sideinnhold / blokkmønstre**. Behold dagens layout ved første overgang. Å velge blokkmønstre endrer hva forsiden viser; det er en egen redaksjonell ombygging som bør prøves på staging.

Forsidevalget i **Innstillinger → Lesing** må også være riktig. Det nye themet endrer ikke dette automatisk. Flere av de videreførte spesialsidene bruker egne maler, så alt du ser på en slik side, trenger ikke komme fra tekstfeltet i sideredigeringen.

### Maler og mønstre

I sidens innstillinger kan du velge en tilgjengelig mal, for eksempel sport eller radio/program. En mal bestemmer rammen. Et mønster settes inn fra blokkverktøyets mønstervelger og gir deg et utgangspunkt du kan redigere. Bytt alltid eksempeltekst før publisering.

Kandidaten bruker klassiske PHP-maler sammen med moderne blokkinnstillinger. Det er derfor ikke et fullstendig dra-og-slipp-theme der hele nettsiden redigeres i WordPress sin nettstedsredigerer.

### Journalist og kilde

Når metadata-utvidelsen er aktiv, finnes feltboksen **Radio Rubben – byline og kilde** ved redigering av innlegg og sider. Her kan du angi `rr-fotball` eller `rr-lokal`, kildenavn, kilde-URL og bekreftet redaktørnavn.

En vanlig menneskeskrevet artikkel skal normalt ha tom journalist-ID og bruke WordPress-forfatteren. Eldre robotartikler kan fortsatt gjenkjennes gjennom sin eksisterende robotmarkør. Å fylle inn en journalist-ID skriver ingen artikkel og starter ingen AI. Den bestemmer hvem saken presenteres som skrevet av.

### Egne utseendeendringer

Vanlig innhold redigerer du i WordPress. Større designendringer gjøres i kildekoden og testes. Ikke lim inn PHP i WordPress sin theme-filredigerer. Et child theme kan bevare lokale kodeendringer ved hovedtheme-oppdateringer, men gjør ikke oppdateringer automatisk kompatible og flytter ikke gamle funksjoner for oss.

## 10. GitHub gir historikk; backup gir gjenoppretting

[Det private Radio Rubben-repositoryet](https://github.com/toystad461/radiorubben-web) lagrer kode og endringshistorikk. [Pull request #28](https://github.com/toystad461/radiorubben-web/pull/28) samler kandidaten og gjør endringene synlige før de tas inn i hovedgrenen.

En **gren** er en egen arbeidsversjon. En **commit** er en lagret kodeendring. En **pull request** er forslaget om å ta endringene inn i hovedversjonen. Et grønt kontrollmerke betyr at de automatiske kontrollene besto; det garanterer ikke at alle Radio Rubben-funksjoner er prøvd.

Den nye `theme-next/`-mappen inngår ikke i eksisterende produksjonspublisering. Repositoryet har likevel en annen publiseringsflyt for eldre WordPress-kode. Derfor skal vi ikke anta at enhver fremtidig sammenslåing i GitHub er uten konsekvens for nettsiden.

Selv om det finnes en egen historikkjobb for publisert innhold, er denne ikke en full databasebackup med medlemmer, stemmer, innstillinger og filer. Vi trenger fortsatt ordinær backup.

## 11. Hvis noe feiler

Stopp videre endringer og noter hvilken side og handling som feilet. Ta gjerne skjermbilde uten personopplysninger. På staging retter og tester vi før vi går videre.

Ved et produksjonsproblem brukes den avtalte tilbakeføringen. Det er ikke alltid nok å aktivere gammelt theme: dersom funksjoner er flyttet til nye plugins, kan begge forsøke å laste samme kode. Tilbakeføring må derfor gjenopprette en kjent fungerende kombinasjon av theme, plugins og innstillinger.

Databasegjenoppretting er et eget steg. En eldre backup kan overskrive nyere artikler, medlemmer eller stemmer. Den skal ikke brukes ukritisk når et tilbakebytte av kode er tilstrekkelig.

## 12. Hva gjenstår før du kan si ja til byttet?

Vi skal kunne svare ja på alle disse punktene:

- Har vi en fersk, fullstendig kopi av dagens kode og data?
- Er funksjonene i gammelt theme kartlagt og flyttet der det er nødvendig?
- Virker de gamle nettadressene og vanlige arbeidsoppgavene på staging?
- Er radio, medlemsfunksjoner, RSS, quiz, speaker og kampfunksjoner prøvd med riktig testoppsett?
- Har du sett og godkjent utseendet på mobil og datamaskin?
- Har vi prøvd tilbakeføring og avtalt hvordan nye data skal bevares?

**Funksjonsflyttingen og den lokale staging-testen er nå utført. Neste steg er de eksterne sluttkontrollene og avtalt produksjonsbytte, beskrevet i stagingrapporten. Du skal fortsatt ikke aktivere kandidaten på produksjon på egen hånd.**

For tekniske detaljer: [migreringsplan](radio-rubben-next/docs/DEPLOY.md), [maloversikt](radio-rubben-next/docs/TEMPLATES.md) og [rr-* / VPS-kontrakten](radio-rubben-next/docs/RR-CONTRACT.md).
