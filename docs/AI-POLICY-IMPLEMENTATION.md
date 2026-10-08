# AI-policy – implementeringsstatus for Web

## Denne PR-en

Base: Web #30, `feature/bremnes-youth-weekly`,
`e7452072e51884e93e41e131abcbbdd086451bb1`, med Fotballrobot 0.10.5.
Basevalget følger fersk pluginversjon fra WordPress. #30, #63, #67 og øvrige
nettsidegrener er ikke endret. Ingen temaendringer følger denne PR-en.

Implementert:
- Felles `AI-POLICY.md` v1.0 og `AGENTS.md` med bevarte produksjonsgrenser.
- Nye kvalitetskontroller bindes til AI-policyversjon 1.0.0. Manglende, gammel eller
  ugyldig policyversjon sperrer videre publisering inntil ny kontroll er utført.
  Eksisterende tekst-/faktahash, testinnleggssperre og eksplisitte redaktørbeslutninger
  beholdes. Ingen eksisterende innlegg eller kontrollmetadata omskrives automatisk.
- En kampartikkel uten eksplisitt lagret godkjenning krever nå både `publish_posts`
  og `edit_post`. Et anonymt bakgrunnskall med grønn modellkontroll kan ikke publisere.
  Planlagt kjøring uten bruker krever en gyldig eksisterende redaktørbeslutning;
  en gammel kontroll alene gir ikke tillatelse. Standard WordPress-handlinger er
  fortsatt avhengige av WordPress' autentisering og handlingskontroller.
- Faktiske WordPress-filtre testes for policyversjon, manglende publiseringsrettighet,
  endret tekst/kilde, manipulerte kontrollmetadata og bevaring av synlig AI-merking.
- Baseline-CI verifiserer fortsatt de uendrede historiske 0.10.4- og PR30-kvitteringene,
  men PR30-kvitteringen testes nå mot sin fastlåste releasecommit. Deretter testes
  kandidatens faktiske PHP-kode. Å kreve at ny kode er byteidentisk med en gammel
  release ville gjøre enhver legitim oppfølging umulig. Ingen deploy-workflow er endret.

Validering: syntaks for 55 PHP-filer og alle 22 isolerte Fotballrobot-suiter besto.
Kommando: `python3 scripts/test-fotballrobot.py`. Modeller og WordPress er mocket;
ingen artikkel eller e-post sendes. Et reelt WordPress/staging-forsøk gjenstår.

**Utrullingskonsekvens:** Ventende saker med eldre kvalitetsmetadata må kontrolleres
på nytt. Tidligere publiserte artikler røres ikke ved installasjon, men en senere
oppdatering av dem må også oppfylle de nye kravene. Bruk ny avgrenset release etter
godkjenning; ikke bruk PR30s historiske installasjonspakke for denne endringen.

## Kartlagt før endring – 2026-10-08

Egne kloner og grenen `policy/ai-v1` brukes fordi prosjektmappen ikke er et Git-repo
(managed-worktree svarte «Not a git repository»). Andre grener og lokale endringer
ble ikke skrevet til. Åpne PR-er og alle fjernreferanser ble lest fra GitHub.

| Repo | main ved kartlegging | Åpne PR-er | Relevant arbeid |
| --- | --- | --- | --- |
| Robot | `e9fcdab676c58fbf4bd017e1a1beace64011e197` | 2 | #1 import; #3 språk-/kvalitetskontroll |
| Studio | `1aff7adeb2b90c9576265c70f06bab141a1519a0` | 11 | #51 læring → #55 AI-journalist; #53 direkte regler overlapper; #52 produksjonsavstemming; #47/#48 app/CRM; #21 Render-test |
| Web | `9006e82e1ebc91e2a128cf2d3e263470e16db8e0` | 30 | #63 historisk 0.10.4 → #30 0.10.5; #67 avstemming → #68–71 nettside/quiz; #72 blokktema; #29–49 historisk fotballkjede |

Studio bruker PHP på Uniweb. Siste dokumenterte deploykjøring
[37739713565](https://github.com/toystad461/radiorubben-studio/actions/runs/37739713565)
ble lest på nytt: success, main `1aff7ad`, 2026-10-08 06:50 UTC.
PR #55 dokumenterer kontroll av 491 faktiske filer kl. 14:36 UTC og ni bevarte
kilde/runtime-forskjeller. Denne oppgaven har ikke gjentatt hele serverhashkontrollen.
`studio-release.yml` kan deploye fra main. Render-PR-en er ikke produksjonsbevis.

WordPress ble lest via autentisert GET `/wp/v2/plugins`: Fotballrobot **0.10.5**
er aktiv. Spillerwidget er 1.3.0 og Site Functions 1.0.0-rc.4. Dette bekrefter
versjoner/aktiv status, ikke byteidentitet av alle filer. Derfor brukes Web #30
som fotballbase, ikke #67s eldre 0.10.4-kandidat. TypeScript-Robot er ikke påvist
som aktiv produksjonsjournalist; Studio har sin egen RSS-flyt.

Ved første kartlegging ga GitHub rulesets-lesing **403 med krav om GitHub Pro
eller offentlig repo**. Etter brukerens uttrykkelige bestilling ble alle tre
repoene gjort offentlige. Blokkeringen er løst, og følgende regler er lest tilbake
som aktive på `refs/heads/main`:

| Repo | Påkrevde kontroller | Ruleset |
| --- | --- | --- |
| Robot | `test` | 24734546 |
| Studio | `verify (8.2)`, `verify (8.4)`, `mobile` | 24734566 |
| Web | `kontroller` | 24734571 |

Alle tre krever PR og avklarte review-tråder. Ingen bypass-aktører er lagt inn.
Det kreves ikke en ekstra godkjenner; eieren kan selv behandle egne PR-er.
Reglene gjelder hovedgrenen, ikke eksisterende utviklingsgrener. Web-kontrollen
`kontroller` er den generelle test-/byggejobben; Fotballrobotens målrettede
PHP-kontroll kjører separat og er ikke påkrevd globalt fordi den har stiavgrensning.
Denne leveransens målrettede kontroller må derfor også være grønne før integrasjon.
Grønne GitHub-kontroller gir fortsatt ingen tillatelse til produksjonsdeploy.

## Dokumentert krav, ikke ferdig implementert

- Komplett mediekontrakt for syntetisk tale og AI-bilder, med filhash, korrekt
  merking i hver kanal, godkjenning av ferdig medium og negative publiseringstester.
  Policyfeltet alene dekker ikke dette. Studio #56 omfatter også policyfelt og
  sperrer for syntetisk lyd på RR Audio-basen. Hørbar lytterinformasjon og full
  AI-bildemerking gjenstår fortsatt.
- Full produksjonsavstemming, ende-til-ende-test mot autentisering/WordPress,
  reelle modellsvar og redaksjonelt review av kvalitet. Mockede modellsvar måler
  sperrer, ikke modellens sannhetsgehalt.
- Publikumsversjonen på «Om oss». Denne PR-en publiserer ingen nettsidetekst.

## Utrullingsgrense

Ingen merge til hovedgren, deploy, workflow-dispatch, artikkelpublisering, læringsaktivering
eller endring av produksjonsdata. En separat godkjent utrulling må avstemme ferske
serverhasher, eksakt filsett, avhengigheter, backup og tilbakeføring. Ikke last opp
en hel gren. Historiske release-manifester skal ikke omskrives til den nye koden.
