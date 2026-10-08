# Produksjonsavstemming – 8. oktober 2026

## Status og mandat
Thomas ba om å avstemme produksjonen mot GitHub og samle det fungerende
grunnlaget. Denne grenen samler eksisterende, dokumenterte kildeobjekter.
Den er **en kladd til samlet kildegrunnlag, ikke en fullstendig verifisert
produksjonskopi og ikke en utgivelse som er godkjent for opplasting**.
Ingen main-merge, serverendring, artikkelpublisering, sletting eller
legitimasjonsendring inngår. Eksisterende produksjonsjobber er ikke deaktivert.

## Hva som faktisk er samlet
Utgangspunktet er den rene PR #63-commiten
`75326d1c26cd3b30f7c52ead8246b4e99df950a4`, direkte etter main
`9006e82e1ebc91e2a128cf2d3e263470e16db8e0`.
Den inneholder Fotballrobotens historisk dokumenterte 0.10.4-kode, tester og
filspesifikke produksjonsbelegg. Hele dette grunnlaget beholdes.

Bare `theme-next/` erstattes med nøyaktig Git-tre
`aaf6f5fd7b0999b6ade1cd02050089bccb9373f2` fra PR #66-head
`d7afe130faf55a7907a039df8cefb9adea436434`. Dermed følger både Next-grunnlaget
fra #64, Site Functions, rettelsene av spillerhook/footer og den publiserte
nyhetsprioriteringen med, sammen med eksisterende lokale tester og dokumentasjon.
Kildebytes omskrives ikke. Temaområdet inneholder også historisk støtte-/testkode;
plasseringen i denne grenen betyr ikke at alle medfølgende komponenter er aktive.

PR #66 sine rot-workflows og engangsutrullingsskript importeres ikke.
Rotens workflows og scripts beholdes fra #63. Denne oppgaven legger ikke til
noen ekstern fjernkjøring eller nye deployveier. Nye arbeidsregler og
`production-candidate.json` viser nøyaktig kildevalg og åpne kontroller.

## Bekreftede historiske belegg

| Komponent | Kilde | Belegg og begrensning |
|---|---|---|
| Next/Site Functions | #64, `f7cce8c4129e68361e65e86928aca71c24140026` | Produksjonsrapport 6. oktober beskriver installasjon og senere Next rc.5. Ingen ny komplett serverkontroll her. |
| Nyhetsprioritering | Runtime `332e443349a79d225f7b659ee22e798a831ef7df` | Actions 37681748734 publiserte tre filer 7. oktober kl. 22:24:45 Oslo. Separate offentlig-kontroller i 37682127618. |
| Fotballrobot | 0.10.4, `29e49e75b7890dd885050b4ad83d97ad266288fa` | #63 rekonstruerer 32 runtime-filer mot tidligere release 37243206615. Dette er ikke bekreftelse på dagens versjon. |
| Studio, eget repo | `1aff7adeb2b90c9576265c70f06bab141a1519a0` | Actions 37739713565/job 113187636289 rapporterer fire selektivt publiserte filer 8. oktober kl. 08:50:49 Oslo. Hele pakken er ikke dermed identisk med serveren. |

Next-kildens eksakte undertre er
`d1b93fe9acfb30a7cbf32a8b1d111155581a3753`; Site Functions er
`b9791f63c8dee62651dac27f615e9fb52ad5fe48`.
Kontrollsummer for de tre publiserte nyhetsfilene er tatt fra utgivelseskvitteringen
og er registrert i JSON-filen. De er historiske etter-hasher, ikke målinger
foretatt på serveren i denne oppgaven.

## Viktige uavklarte forskjeller

**Fotballrobot:** Studio-status omtaler en separat 0.10.5-linje. Et commitsøk
etter versjonsteksten ga ingen treff; det avgjør ikke om versjonen finnes eller
er publisert. 0.10.4 må ikke rulles ut som erstatning før aktuell pluginversjon,
filhasher og eventuelle senere utgivelsesbelegg er avstemt.

**Spillerwidget:** PR #61 beskriver 1.3.1, men det kontrollerte historiske
utrullingsforsøket startet ikke jobbtrinn. En merge inn i en foreldregren er
ikke deploybevis. Kandidaten henter ikke inn denne widgetversjonen på antakelse.
Aktiv widget og dens filgrunnlag er fortsatt en ekstern avhengighet.

**Studio:** Den siste deployloggen viser først en bred `dry-run`, deretter en
separat faktisk selektiv publisering av fire filer. Dry-run-listen er ikke en
liste over alt som ble lastet opp. Den viser blant annet forskjeller for
bootstrap, head, innloggingsfiler og logo. Historiske source/live-hasher finnes
i Studio sitt `scripts/studio-deploy-baseline.json`; de må ikke gjøres like ved
å endre manifestet uten å kontrollere de faktiske filene. Privat konfigurasjon,
leverandøravhengigheter og øvrige data forblir utenfor denne kodekonsolideringen.

**Resten av nettstedet:** Aktiveringsstatus, databasebaserte kodebiter,
mu-plugins, cron-eierskap, driftsdata og eventuelle server-only-filer er ikke
ferskt kartlagt. En samlet kildekandidat erstatter ikke dette arbeidet.

## Begrenset tilgang i denne arbeidsøkten
Forsøket på å opprette en ny SSH-basert lesekontroll ble stoppet av
verktøyets sikkerhetskontroll. Den ble ikke opprettet eller kjørt.
Denne grenen inneholder ikke den blokkerte kontrollen.
Offentlige kontrollforsøk ga heller ikke ferskt verifiserbare produksjonsbytes:
nettleserverktøyet viste eldre indeksert innhold, og lokal HTTP-kontroll fikk
DNS-feil i arbeidsmiljøet. Dette er ikke bevis for at Radio Rubben er nede.
Ingen fersk full serveravstemming påstås.

## Holdt utenfor
Web #65 (mobilheader) er ikke publisert ifølge kontrollert PR-status og tas ikke
med. Studio #47 (privat app), #48 (CRM), #51 (ny læring), Render-flytting og
nye funksjoner inngår ikke. Eksisterende PR-er og grener bevares inntil deres
kode og historikk er erstattet på en dokumenterbar måte.

## Kontroller før denne grenen kan bli hovedgrunnlag
1. Verifiser nye Git-tre mot de fastlåste kildene og kjør eksisterende
   Fotballrobot-/Next-tester på den samlede kildecommiten. Eldre grønn CI er
   ikke ny integrasjonstest. Testresultat føres på leverings-PR.
2. Gjennomfør en separat godkjent, fersk produksjonsinventering: kodehasher,
   aktivering og avhengigheter. Registrer hvert avvik med kildebelegg.
3. Avstem særlig nyere robotlinje, aktiv widget og Studio-avvik. Nye eller
   ukjente filer skal ikke overskrives med eldre Git-versjoner.
4. Kontroller deployutløsere før eventuell main-merge. Eksisterende
   `wordpress.yml` kan publisere ved main-push hvis variablene tillater det,
   og det gamle deployskriptet peker på det gamle temaet, ikke Next.
5. Behold manuell sluttgodkjenning og verifiser tilbakeføring før separat
   godkjent produksjonsutrulling. Den tidligere avgrensede backup-fravikelsen
   for tre Next-filer gjelder ikke automatisk en større opprydding.

## Arkiv og ansvar
GitHub inneholder kode, filmanifest, tester og utgivelsesbevis. Studio har sitt
eget repository; denne rapporten refererer til det, den kopierer ikke Studio.
OneDrive sin eksisterende `Radio Rubben/System & Kvalitet/AI og arbeidsregler`
er knutepunkt for arbeidsrutinen. Rapporten er ikke automatisk synkronisert dit.
Ingen data, innstillinger, DNS, Entra, e-post, strømoppsett eller publiserte
artikler er endret som del av denne kildegrenen.

## Kilder
- [Web #63](https://github.com/toystad461/radiorubben-web/pull/63)
- [Web #64](https://github.com/toystad461/radiorubben-web/pull/64)
- [Web #66](https://github.com/toystad461/radiorubben-web/pull/66)
- [Next produksjonsrapport](../theme-next/docs/PRODUKSJON-2026-10-06.md)
- [Robotens produksjonsgrunnlag](fotballrobot/production-baseline.md)
- [Next publisering](https://github.com/toystad461/radiorubben-web/actions/runs/37681748734)
- [Studio publisering](https://github.com/toystad461/radiorubben-studio/actions/runs/37739713565)
- [Studio kilde](https://github.com/toystad461/radiorubben-studio/tree/1aff7adeb2b90c9576265c70f06bab141a1519a0)
