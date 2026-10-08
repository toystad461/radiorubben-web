# Radio Rubben Web – arbeidsregler

## Start her
Les `docs/PRODUCTION-RECONCILIATION-2026-10-08.md` og
`docs/production-candidate.json` før videre arbeid. Dette er en samlet
kildekandidat, ikke en fersk fullstendig kopi av produksjon.
Historiske README-, PR- og deploynotater er ikke alene bevis på dagens drift.
Hent fersk main/head, gjeldende utgivelsesbevis og relevante tester.

## Avgrensning
Arbeid på én avgrenset gren. Bevar ukommittert arbeid og historikk.
Ikke force-push, massemerge eller lukk eldre PR-er uten dokumentert erstatning.
Skill produksjonsavstemming fra nye funksjoner. PR #65 er ikke del av denne
kandidaten. Fotballrobot 0.10.4 er historisk dokumentert; senere 0.10.5 og
spillerwidgetens aktuelle runtime må undersøkes før den regnes som fasit.

## Ingen implisitt utrulling
Denne konsolideringen gir ikke tillatelse til main-merge eller produksjonsdeploy.
Eksisterende deploy-workflows og variabler er ikke deaktivert av denne teksten.
En main-push kan utløse utrulling. Ikke kjør det gamle WordPress-temaets apply,
last opp hele main eller en hel feature-gren over produksjon.
Før separat autorisert utrulling kreves ferske serverhasher, eksakt filsett,
grønne tester på kildecommiten, verifisert tilbakeføring og korrekt målmappe.
Ingen WPVibe-kodeendring som alternativ til en blokkert kontroll.

## Data og publisering
Bevar artikler, bilder, brukere, avstemninger, godkjenningskøer, kildebelegg,
redaksjonelle regler, historikk og manuell sluttgodkjenning.
Ingen databasekopier, innloggingsdata, privat konfigurasjon eller produksjonskøer
i Git eller testartefakter. Ikke endre DNS, e-post, Entra, cron, Radio.co eller
andre repoer som skjult følge av kodearbeid. Studio har eget repository.

## Levering
Rapporter separat: endret kode, kildeidentitet, tester, historiske deploybevis,
fersk produksjonskontroll, uavklarte avvik og faktisk publisering.
GitHub er kodearkivet. OneDrive er drifts-/mediearkivet; bruk verifiserte
filreferanser og skill lokal rapport fra faktisk overføring til OneDrive.
