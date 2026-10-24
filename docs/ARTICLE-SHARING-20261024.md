# Artikkeldeling – 24.10.2026

Arbeidsområder: IT & Utstyr / Sosiale Medier / System & Kvalitet.

## Problem og avgrensning

Thomas melder at Facebook åpnes uten artikkelen i et nytt innlegg. Aktivt
tema er Radio Rubben Next 2.0.0-rc.5, verifisert gjennom WPVibe 24.10.2026.
Den eksisterende Facebook-knappen er en vanlig lenke til sharer.php, uten
håndtering av overgangen til en installert app. Dette er en mulig forklaring
på den rapporterte mobilfeilen; feilen er ikke reprodusert på fysisk iPhone.

Tre publiserte artikler ble kontrollert: sesongoppsummeringen (1466),
influensasaken (1463) og ukeoversikten (1398). Alle har riktig artikkel-URL i
Facebook, WhatsApp og e-post. Sesongoppsummeringen har canonical, og:url,
og:title, og:description og og:image. Det er ikke funnet en manglende URL
eller en generell kanalfeil i disse sidene.

Direkte nettleserkontroll på sesongartikkelen:

| Kanal | Bekreftet | Begrensning |
| --- | --- | --- |
| Facebook | Knappen åpner sharer.php med korrekt URL | Facebook krever innlogging i testnettleseren. Innleggsskjermen og iPhone-overgangen er ikke verifisert. |
| WhatsApp | Lenken inneholder korrekt tittel og URL | Overgangen krever appprotokoll som testnettleseren ikke tillater. Ingen sluttkontroll i app. |
| E-post | Emne, tekst og URL er korrekt kodet | Ingen sending eller kontroll i en installert e-postapp. |
| Kopier | Klikk viser «Lenken er kopiert!» | Eksakt utklippstavleinnhold er testet med isolert API; systemutklippstavlen ga ikke lesbart innhold i nettleserverktøyet. |
| Enhetens delingsmeny | Eksisterende kode sender tittel og URL | Ikke tilgjengelig i denne desktop-testnettleseren. |

Det finnes ikke egne artikkelknapper for Instagram, Snapchat, LinkedIn eller
X i den kontrollerte delingsmodulen. Enhetens delingsmeny kan tilby installerte
apper som tar imot lenker; tilgjengelighet og mottatt forhåndsvisning avgjøres
av enheten og appen. Vi lover ikke opprettelse av Instagram Story eller annen
automatisk kanalpublisering.

## Endring

Kun én fil skal eventuelt installeres:
`themes/radio-rubben-next/template-parts/article-share.php` under wp-content.

- Synlig tekst på «Del artikkelen» når enhetens delingsmeny støttes.
- Facebook-klikk åpner samme delingsmeny med tittel og artikkel-URL. Brukeren
  velger Facebook. Knappens tilgjengelighetsnavn forklarer dette.
- Egen Facebook-nettlenke beholdes når menyen brukes. Uten støtte/JavaScript
  fungerer den opprinnelige Facebook-knappen som før.
- Deling kalles direkte fra klikket for å bevare brukeraktivering.
- Avbryt lukker uten automatisk navigasjon, kopiering eller påstått publisering.
- Feil viser en markerbar artikkellenke. Doble klikk åpner ikke flere menyer.
- HTML-entiteter i overskriften dekodes før kanalenes URL-koding.
- WhatsApp og e-post beholder sine eksisterende delingsadresser.

Inaktivt eldre tema, quizdeling, robot, artikler, metadata, medier, Studio og
publiseringsinnstillinger endres ikke.

## Kilde og kontroller

Base: main `5169765446b75f5c983695940241985beda2f7ad`.
Lesbar live-kilde for denne malen samsvarte med main. WPVibe advarer om
redigering/maskering i kildevisningen; dette er ikke et bytebevis for deploy.

SHA-256 for Git-baseline:
`ecaef0c3db0738760c4f69a687447955f0341a63a947163a080ae3b05ce2f3e1`.
SHA-256 for kandidaten:
`3452ca0eb3a9cccf2eb703514259e73dc4ad1be613e0e384585011abf9bbd966`.

20 isolerte tester består lokalt: delingsdata, kanalkoding og norske tegn,
synkron brukeraktivering, ingen støtte, canShare-avvisning, avbrudd, feil,
dobbeltklikk, nye faner, kopieringsfeil og flere malinstanser. PHP-rendering
kjøres med PHP-WASM 8.5.10. De samme testene inngår i Next-workflowen med
runnerens native PHP. Dette er API-/maltester, ikke bevis på Facebook-appen.

Kjør fra repoets rot:

```sh
php theme-next/tests/article-share-render.php > /tmp/rr-article-share.json
RR_SHARE_RENDER=/tmp/rr-article-share.json node --test theme-next/tests/article-share.cjs
python3 theme-next/scripts/check.py
```

Referanser kontrollert 24.10.2026:
- [W3C Web Share](https://www.w3.org/TR/web-share/): chooser, title/url,
  brukeraktivering og plattformbestemte mål.
- [WhatsApp click to chat](https://faq.whatsapp.com/425247423114725):
  `https://wa.me/?text=` med URL-kodet tekst.
- [Meta Share Dialog](https://developers.facebook.com/documentation/sharing/reference/share-dialog).
  Søkeresultat tilgjengelig; full dokumentasjon lot seg ikke hente.

## Utrulling og etterkontroll – ikke utført

Separate produksjonssteg følger AGENTS.md. Ikke last opp hele theme-next eller
main: andre aktive temafiler kan avvike fra main.

1. Frys eksakt testet kildecommit. Kontroller fortsatt aktivt Next og fersk
   serverhash for den ene filen mot Git-baseline. Stopp ved avvik.
2. Ta privat backup av filen utenfor webrot. Verifiser backuphash og at
   tilbakeføring bare berører denne filen; ingen databasegjenoppretting.
3. Installer atomisk fra eksakt kandidat. Kontroller filhash, tøm relevant
   WordPress-sidecache, hent de tre artiklene på nytt og kontroller metadata
   og delingsparametere. Bevar øvrige filhasher.
4. Prøv fysisk iPhone/Safari med Facebook installert: Facebook-knapp → velg
   Facebook → innleggsskjerm med riktig artikkellenke. Avbryt før publisering.
   Prøv også vanlig nettleser uten Web Share, Android, WhatsApp, e-post,
   kopiering og 320/390 px layout. Ikke publiser/sende noe som test.
5. Ved regresjon: tilbakefør kun filen fra privat backup hvis serverens fil
   fortsatt matcher kandidaten, tøm sidecache og kontroller gamle knapper.

Ingen merge, produksjonsutrulling eller sosial publisering er utført her.
