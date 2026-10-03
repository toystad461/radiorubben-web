# Fotballrobot 0.9.3 – startoppstilling fra konkret NFF-kamp

Aktivert på radiorubben.no 3. oktober 2026. [PR #39](https://github.com/toystad461/radiorubben-web/pull/39) bygger separat på den aktive AI-merkingsrettelsen i #38. Ingen main-merge eller bred utrulling.

## Feil og retting

Robot::refresh hentet lagoppstillinger fra rr_poll_match-kopien i speaker-/avstemningssystemet. Den kunne være tom selv når NFF hadde startoppstillingen. Writer hoppet da lydløst over lagavsnittet.

Lineups::parse leser nå lagoppstillingen fra samme NFF-respons som kampfakta, bundet til kamp-ID og hjemme-/bortelag-ID. Spiller-ID, draktnummer, roller, rekkefølge, duplikater, strøkne spillere og antall startspillere kontrolleres. Ingen avhengighet til valgt dashboardkamp. Avvik gir ukjent oppstilling og synlige redaktørvarsler; reserver blir aldri behandlet som dokumenterte innhopp.

Lineups::paragraph bruker bare Bremnes Menn A 30365 eller Kvinner A 48835, uavhengig av hjemme/borte. Ett avsnitt har fet «Bremnes:», etternavn i kilderekkefølge, initialer ved like etternavn, bevarte bindestreksnavn og «(Innbyttere: …)» med font-size:0.85em;line-height:1.5 i samme avsnitt. Manglende lister får eksplisitt tekst. Writer setter avsnittet før kort/hendelser og instruerer nye tekster til å holde hendelsene i eget avsnitt.

Den automatiske forberedelsesfasen henter nye kampfakta før generering og bruker dermed den nye adapteren. Allerede opprettede utkast og publiserte artikler blir ikke regenerert eller overskrevet. Manuell sluttgodkjenning, kilde-/språkkontroll, duplikatsperrer, AI-merking og manuelt bildevalg er bevart.

## Verifikasjon

- 33 nye kontroller av kildehenting, identitet, begge lag-ID-er, begge kampretninger, navn, fallback og HTML-plassering.
- Hele eksisterende testsuiten er grønn, inkludert 52 publiseringskontroller og 28 redaksjonelle kontroller.
- [CI 37141600447](https://github.com/toystad461/radiorubben-web/actions/runs/37141600447): bestått.
- [Read-only kildekontroll 37141597444](https://github.com/toystad461/radiorubben-web/actions/runs/37141597444): bestått på serveren mot faktiske NFF-sider.
- Bremnes–Lyngbø 8984418: damer, hjemmelag, 11 startspillere og 3 reserver; navn og ferdig HTML samsvarer med separat kildekontroll.
- Juristforeningen–Bremnes 8985483: herrer, bortelag, 11 startspillere og 5 reserver.
- Første CI avdekket en feil i testforventningen: når reservelisten mangler, er Sortland alene om etternavnet og skal ikke ha initial. Testen ble rettet; produksjonslogikken var korrekt.

## Selektiv utrulling

Testet og aktiv kode: `097928615bdc900738b98b9be68e79f6f7e20a85`.

[Utrulling 37141769345](https://github.com/toystad461/radiorubben-web/actions/runs/37141769345) er bekreftet vellykket. Bare includes/lineups.php, includes/robot.php, includes/writer.php og pluginens hovedfil ble installert. Hele eksisterende PHP-baseline ble kontrollert først; kandidatens fire filer ble kontrollert mot hasher fra vellykket kildeprøve. Privat sikkerhetskopi og rollback inngår i scripts/deploy-fotballrobot-lineups.sh.

Backup på serveren: `~/.radiorubben-deploy/backups/fotballrobot-lineups-097928615bdc900738b98b9be68e79f6f7e20a85/code.tar.gz`.

WP REST ble lest tilbake: version=0.9.3, rules_version=1.0.0, delay_seconds=3600, enabled=true. Post 1073 beholdt identisk tekst/hash og status publish. Ingen kampartikkel ble endret eller publisert under denne kodeendringen.

Release-scriptet er en låst engangsutrulling fra 0.9.2 og skal ikke gjenbrukes som generell deploy. Senere endringer krever ny baseline og testet kandidat.
