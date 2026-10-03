# Fotballrobot 0.9.2 – godkjent AI-merking

## Aktiv rettelse

Thomas bestilte rettelsen og hadde allerede godkjent publisering av innlegg
1073, Bremnes–Lyngbø (kamp 8984418). Den lagrede artikkelens AI-merking ble
feilaktig sendt til kampfaktakontrollen, som avviste redaksjonelt ansvar fordi
rollen ikke fantes i NFF-faktapakken.

`EditorialNotice::BLOCK` er nå én felles, godkjent standardmal for både ny
artikkelgenerering og kontroll av lagret tekst. Bare denne eksakte HTML-blokken
helt først i artikkelen utelates fra modellens kontrollkopi. Innledende vanlig
blankplass aksepteres. Endret tekst, navn eller HTML, ekstra påstander, flere
bokser og merking andre steder går fortsatt til faktakontroll. Ingen generell
klasse- eller DOM-basert unntaksregel brukes.

Hele den lagrede artikkelen, inkludert merkingen, er fortsatt bundet av
publiseringskontrollens innholdshash. Endret merking ugyldiggjør godkjenningen.
Merkingen fjernes ikke fra WordPress eller visningen. Artikkeltekst og
faktapakke endres ikke av denne rettelsen. Personlig godkjenningsmerking er
ikke lagt til som et automatisk unntak.

## Kontroller og produksjon

- Kodekandidat: `4f5af6467196a10a96775262dcd2312f6447b799`.
- [Integrasjonstester](https://github.com/toystad461/radiorubben-web/actions/runs/37140052202): bestått,
  inkludert 52 publiseringskontroller, parser, skriving, språkvask, spillerflyt,
  rapport, oppstart og køkontroll. Første kjøring fant en gammel forventning
  om CSS-versjon 0.9.1 i testen; denne ble oppdatert til 0.9.2.
- [Prøve mot lagret utkast](https://github.com/toystad461/radiorubben-web/actions/runs/37140048088): bestått.
  Kandidatkode ble lastet utenfor webroten i en separat native WP-CLI-prosess.
  Faktisk faktakontroll og språkvask godkjente utkast 1073. Kontrollmetadata ble
  lagret gjennom robotens normale funksjon; artikkeltekst og draft-status var
  uendret. E-post og andre utgående HTTP-kall var blokkert i prøveprosessen.
- [Selektiv utrulling](https://github.com/toystad461/radiorubben-web/actions/runs/37140203259): bestått.
  Bare writer.php, robot.php, pluginens hovedfil og nye editorial-notice.php
  ble installert. Baseline og kandidatens SHA-256 ble kontrollert før endring.
  Privat kodebackup og automatisk rollback var på plass.
- Autentisert runtime bekreftet version=0.9.2, rules_version=1.0.0,
  delay_seconds=3600 og enabled=true. Den eksisterende automasjonen krever
  minst 0.9.1 og behøvde ingen endring.
- Innlegg 1073 ble deretter publisert etter Thomas' tidligere godkjenning:
  https://www.radiorubben.no/rubben-kamp-8984418/
  Tilbakelesing bekreftet publish, bilde 773 og identisk tittel, innhold og
  utdrag som før rettelsen. Synlig AI-merking er beholdt.

## Versjonssporing og tilbakeføring

[PR #38](https://github.com/toystad461/radiorubben-web/pull/38) bygger separat
på produksjonsgrunnlaget i PR #37. Main og andre PR-er er ikke endret eller
merget. Dette unngår at den brede main-deployen tar med uvedkommende filer.

Backup ligger privat på serveren under
`~/.radiorubben-deploy/backups/fotballrobot-notice-4f5af6467196a10a96775262dcd2312f6447b799/code.tar.gz`.
Tilbakeføring gjelder de fire kodefilene; artikkel og kontrollhistorikk skal
beholdes. De branchbundne engangsjobbene er ikke en generell deploymekanisme.
Ny utrulling krever ny baseline og kandidatprøve.
