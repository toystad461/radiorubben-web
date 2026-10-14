# Nyhetsbilder v1.2 – kontrollert import og publisering

Status 14.10.2026: kode og isolerte tester. Ikke installert eller aktivert i produksjon.
Sammenhørende endring: `radiorubben-studio`, grenen `feat/news-image-pack-v12`.

## Behov og avgrensning

Nyhetsroboten i Studio bruker ett fast bilde (1079). Nye utkast skal i stedet ha
et begrunnet temavalg med tre formatvarianter og eksisterende menneskelig
sluttgodkjenning. Denne utvidelsen eier import, faktisk WordPress-medieidentitet,
filkontroll og WordPress-visning. Studio eier sak, valg og redaksjonell vurdering.
Det kjøres ingen bildeproduksjon. Eksisterende innlegg, bilder og manuelle valg
masseendres ikke. Fotballrobotens separate kø og TypeScript-grunnskjelettet endres ikke.

## Original og faktiske mediekoblinger

Original: vedlagt `RadioRubben-Nyhetsbilder-v1.2.zip`, i denne arbeidsøkten
`/workspace/scratch/816dfaad639c/upload/RadioRubben-Nyhetsbilder-v1.2.zip`.
ZIP SHA-256: `a2d27533d3b9455a96d7c967ed4f87f6aba3827156914a468104bb8b21303adc`.
Register SHA-256: `0f090e476d8042339e1dab75b7653b34548ff24496294bb38908b3db0fabe0da`.

Registeret følger koden byteidentisk under `data/01-BILDEREGISTER.json`.
Originalmediene skal arkiveres separat, ikke genereres på nytt eller hardbeskjæres.
Originalpakken er lastet opp 14.10.2026 under den eksisterende arkivstrukturen:
`Radio Rubben / 02 Profil og maler / Nyhetsbilder / RadioRubben-Nyhetsbilder-v1.2`.
OneDrive-item `012VNUSIGB4FUDOXKHQRAYD3WJSRTTNAJY`, 39 646 211 byte.
Nedlastet igjen fra OneDrive og SHA-256 kontrollert identisk med vedlegget.
Se `docs/NEWS-IMAGES-RELEASE.md` for avgrenset utrulling og driftskvitteringer.

På målserveren kjøres, etter konkret godkjent utrulling:

```bash
wp plugin activate rr-news-images
wp rr-news-images import /absolutt/privat/sti/RadioRubben-Nyhetsbilder-v1.2.zip
wp rr-news-images verify
wp option get rrni_package_receipt --format=json
```

Stien i eksemplet er en plassholder, ikke en påstått serverfil. Importkvitteringen
lagrer den **faktiske** arkivstien, mediekatalogen, pakkehash og tidspunkt.
Filer ligger under WordPress sitt faktiske `wp_upload_dir().basedir` i
`rr-news-images/RadioRubben-Nyhetsbilder-v1.2/`. Hvert vedlegg har identitet basert
på asset_id, pakkeversjon og SHA-256. Katalog-API-et returnerer bare medie-ID-er og
URL-er slått opp fra virkelige vedlegg. Det returnerer ikke interne filstier.

Hele pakken valideres før første skriving. Importlåsen er en eksklusiv fil-lås i
samme medielager; flere prosesser på denne verten deler den. Ved distribuert
utrulling må vertene dele et lager med fungerende fil-låsing. Dette er ikke prøvd.
Identiteten ligger i selve vedleggets første databaseinnsetting, slik at avbrudd
før meta-/filskriving kan gjenopptas. Ferdige medier som er endret, eller som ligger
i papirkurven, overskrives/dupliseres ikke. De må avklares manuelt.

`GET /wp-json/rr-news-images/v1/catalog` krever eksisterende WordPress-lesetilgang
med `edit_posts`. Det kontrollerer alle 21 filhash-er, dimensjoner, opphav og
bildetekster. Ingen API-nøkkel sendes til nettleseren.

## Publisering og presentasjon

Studio sender `rr_news_image` sammen med eksisterende `/wp/v2/posts`-operasjon.
Publisering krever en eksplisitt menneskelig bekreftelse, aktør/tidspunkt og
samme verifiserte variantmapping. WordPress kontrollerer egne faktiske filer og
at hovedbildet samsvarer før lagring. Et endret WP-redaktørbilde bevares og gir
konflikt ved gammel Studio-publisering. Draft-redigering er fortsatt mulig.
Denne flyten støtter utkast og direkte sluttgodkjent publisering, ikke planlagt
publisering. Kildeattribusjon håndteres uendret av Studio og er uavhengig av motiv.

`lib/media-policy.php` gjenbruker den rene proveniensmodellen fra web PR75,
commit `17bb060ba10a0b30d4e077b193c137d4fd30751f`, med prefiks endret til
`rrni_mp_`/`RRNI_MP_` for å unngå dobbeltdefinisjoner. Ingen av PR75s globale
publiseringssperrer aktiveres som bieffekt. Hvis den utvidelsen senere aktiveres
separat, oppdateres også dens eksisterende godkjenningskontrakt fra den uttrykkelige
menneskelige godkjenningen. Import gir aldri redaksjonell godkjenning.

Pakkevedlegg bruker hele originalen, ingen WP-crop eller kryssformat-srcset.
Kun disse bildene får contain-visning og synlig standardbildetekst.
Liggende brukes i artikkel/nyhetskort, også på mobil. De tre flatevariantene er
separate komposisjoner. Kvadrat/story lastes ned fra godkjent Studio-sak.
4:5 og 1,91:1 er ikke egne filer: bruk luft/bakgrunn eller manuell tilpasning.
Yoast/Rank Math får originalen for lenkekort når de brukes, men tredjeparts
beskjæring kan ikke garanteres. Optimole 4.2.16s kildekodefiltre bevarer
originaladressen og hopper over optimalisering/lazy-crop for pakkevedlegg.
Aktivt Next-temas prioriterte nyhetskort og doble artikkelbildetekst er håndtert
med CSS avgrenset til pakkevedlegg. Faktisk visning kontrolleres før aktivering.
Ingen NRK-logo eller kommunevåpen legges til.

## Kontroller og ferdigkriterier

- Lokal PHP 8.3.30: syntaks og kontrakttest med fastlåst register.
- Faktisk WordPress 7.1.3 med SQLite i en isolert installasjon: 21 mediefiler,
  identisk gjentatt import, feil midt i import, gjenopptakelse uten duplikater,
  samtidighetslås, endrede filer/alt-tekster, doble identiteter, negative
  publiseringsgrenser og gyldig menneskelig godkjent publisering.
- Test viser at endret WordPress-hovedbilde bevares og stopper foreldet overføring.
- Nettleserkontroll 390/1280 px med faktisk WordPress-bildepipeline og repositoryets
  next-tema: sju nyhetskort og artikkelbildet, alle hele, lastet og uten horisontal
  overflyt. Studio viser tre Bremnes-varianter i samme kontroll. Visuelt inspisert.
  Dette er ikke etterkontroll av produksjonstema eller ekstern sosial beskjæring.
- Ordinær `npm test`/`npm run build` består. Det er ikke en WP-runtime-test.
- Testfil: `tests/wordpress.php`. Krever `RRNI_TEST_ISOLATED=true` i en
  **disponibel** WP-installasjon, og kjøres med `wp eval-file ... /absolutt/pakke.zip`.
  Den endrer/sletter bare syntetiske testdata; må aldri kjøres i produksjon.
- GitHub-jobben kontrollerer PHP 8.2/8.4 og manifestkontrakten. Originalpakken er
  ikke publisert i Git, så den jobben erstatter ikke import-/visningstestene.

Lokal testmedieplass:
`/workspace/scratch/816dfaad639c/runtime/wordpress/wp-content/uploads/rr-news-images/RadioRubben-Nyhetsbilder-v1.2/`.
Denne installasjonen bruker Radio Rubben-hostnavnet med isolert lokal ruting.
Test-ID-er og test-URL-er er ikke produksjonsmedier og skal ikke flyttes inn i Studio.

Før drift: avstem berørte filer mot server, ta privat kodebackup, importer pakken,
verifiser de faktiske medie-ID-ene, kontroller tema/CDN og prøv en ny Studio-sak.
Først deretter aktiveres `news_images_enabled`. Kodeutrulling og redaksjonell
publisering er to separate godkjenninger. Tilbakeføring starter ved å slå av
nytt automatvalg; behold allerede lagrede sakvalg og mediefiler. Ikke slett
vedlegg brukt av godkjente saker. Full kode-tilbakeføring må ta hensyn til slike saker.

Dokumenterte WordPress-kroker: [image_downsize](https://developer.wordpress.org/reference/hooks/image_downsize/),
[intermediate_image_sizes_advanced](https://developer.wordpress.org/reference/hooks/intermediate_image_sizes_advanced/),
[REST før lagring](https://developer.wordpress.org/reference/hooks/rest_pre_insert_this-post_type/).
