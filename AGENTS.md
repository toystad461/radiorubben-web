# Radio Rubben Web – arbeidsregler for Codex

## Start her
Les docs/DEVELOPMENT.md og relevant områdedokumentasjon. Kontroller fersk main,
PR-head/base og hvilket filsett som faktisk er i drift før kode eller utrulling.
Historiske README-, PR- og deploynotater er ikke alene bevis på dagens drift.
Dette repoet eier WordPress/nettside; Studio har eget repository.

## Arbeidsmåte
- Arbeid i en avgrenset gren og en gjennomgåbar PR med konkret ferdigkriterium.
- Bruk main for uavhengig arbeid; dokumenter nødvendige PR-avhengigheter.
- Bevar ukommittert arbeid og historikk. Ingen force-push, massemerge eller
  lukking av eldre PR-er uten dokumentert erstatning.
- Gjenbruk eksisterende arkitektur. Avklar aktivt tema/plugin før redigering;
  det finnes både eldre tema, Next og flere quiz-implementasjoner.
- Bruk eksisterende tester for det berørte området. npm test alene tester
  gammel statisk nettside, ikke WordPress/Fotballrobot/quiz.
- Rapportér endret, testet, uprøvd, merget og faktisk utrullet separat.
- Fortsett med allerede autorisert arbeid; ikke krev samme bekreftelse igjen.

## Produksjon og data
Main kan utløse utrulling. Les aktuell workflow og sjekk gitt autorisasjon.
Ikke last opp hele main eller en hel feature-gren over produksjon. Full
Fotballrobot er ikke samlet på main ved audit 21.10.2026; se utviklingsoversikten.
Før autorisert deploy: ferske serverhasher, eksakt kildecommit/filsett,
relevante grønne tester, privat backup, riktig mål og verifisert tilbakeføring.
Bevar artikler, bilder, brukere, avstemninger, godkjenningskøer, manuelle rettelser,
kilder og historikk. Ingen private data, tokens eller databasekopier i Git/artefakter.
Ikke endre DNS, e-post, Entra, cron eller andre repoer som skjult del av arbeidet.
Bruk testmiljø og syntetiske data. Ikke publiser ekte saker eller påvirk avstemning
som test. Tilgangsproblemer skal beskrives og ikke omgås via annen publiseringsvei.

## Redaksjon og lyd
Skriv bokmål med Radio Rubbens lokale tone. Ingen oppdiktede fakta/sitater/resultater.
Bevar kildeidentitet, tidspunkt, belegg og innholdsversjon. Nyhetspublisering
krever redaksjonell godkjenning; endret tekst/kilde/medium må kontrolleres på nytt.
AI-kontroll er ikke redaktørgodkjenning. Læringseksempler er ikke nye faktakilder.
TTS og sendekø skal kunne automatiseres uten obligatorisk godkjenning av hvert
innslag etter brukerens avklaring 19.10.2026. Dette er et produktmål; aktiver
ikke automatikk eller fjern eksisterende runtime-sperrer som del av en kodeaudit.
Ny playout skal ikke kreve radio.co. Bevar eksisterende integrasjoner inntil
en konkret erstatning er bestilt, testet og satt i drift.

## Code Review Rules
- Flag tap av redaktørrettelser, kildebelegg eller godkjenning ved retry/oppdatering.
- Flag deploy som kan overskrive ukjent serverkode eller runtime-data; krev
  avstemt filmanifest og en tilbakeføring som bevarer nye data.
- Ikke godta grønn statisk nettsidekontroll som bevis for WordPress-funksjonalitet.
