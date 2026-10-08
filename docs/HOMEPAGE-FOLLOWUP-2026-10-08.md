# Oppfølging av forside og nyhetsside – 8. oktober 2026

Publisert 8. oktober 2026 kl.13:56:39 norsk tid via selektiv GitHub/SSH-jobb.

- Forsidens neste kamp får lokale klubblogoer fra eksisterende kampdata. Manglende logo skjules; hjemme/borte og lagnavn beholdes.
- Værboksen komprimeres til stedsvalg, ikon, temperatur, kort beskrivelse og Yr-lenke. Stedssøk og kildehenvisning beholdes. Hele timevarselet skjules slik at ingen tom overskrift står igjen.
- Nyhetssidens sidetittel får 30–42 px og korttitlene 21–25 px, 22 px på mobil. Stilarket gjelder kun nyhetsoversikten.

GitHub er kildearkiv. Basert på publisert forsiderefinement fra PR68; ingen main-merge.

WPVibe-utkast: CSS ble lagret, men vertens sikkerhetsfilter avviste ny sidebar-match.php og enqueue-endringen i page-nyheter.php med HTTP403 Blocked, kl.11:29:29 og11:30:04 UTC. Lesing bekreftet at endringene manglet. Draft-sidebar ble derfor tilbakeført til eksisterende kampkomponent for å unngå feil. Ingen ny forespørsel for blokkerte endringer og ingen omgåelse ble forsøkt. Utkastets minifiserte CSS viste også gammel værpresentasjon, så visuell godkjenning er ikke ferdig.

Npm test og bygg bestått. GitHub-kontroller må også være grønne før eventuell utgivelse. Publisering krever ferdig forhåndsvisning, ferske serverhasher og tilbakeføringsbevis. Live-siden er uendret av denne oppfølgingen.

## Autorisert publiseringsvei

Brukeren godkjente direkte GitHub/SSH-publisering med «gjør det». Lokal nettleserkontroll med eksempeldata viste begge faktiske klubblogoene, temperatur 38 px, skjult timevarsel og sidetittel 38,4 px/korttittel 21,76 px ved 1280 px. Ved mobilbredde 390 px var sidetittel 30 px/korttittel 22 px og dokumentbredden 375 px uten horisontal overløp. Dette er CSS-kontroll med eksempeldata, ikke en full lokal WordPress-kopi. Lastrekkefølge i WordPress er sikret med stilarkavhengighet.

Utgivelsen er låst til seks temafiler fra kildecommit 90f88666533684165813513fe4b7a01bc021fb7c. Serveren sammenlignes med PR68-grunnlaget 2b4779d; rå SHA256 registreres og backup verifiseres. Alle øvrige temafiler kontrolleres uendret. Tilbakeføring testes i et midlertidig filsystem før SSH-nøkler lastes. Cache oppdateres via WP-Optimize sine eksisterende metoder; sikkerhetsfilteret endres ikke.

## Faktisk utgivelse og fersk kontroll

- Kildecommit: `90f88666533684165813513fe4b7a01bc021fb7c`. Alle fire kildekontroller bestod.
- Leveringscommit: `3825d780c5043570d6f7fab2514424866da43fec`.
- Vellykket utgivelsesjobb: https://github.com/toystad461/radiorubben-web/actions/runs/37773371431
- Backup: `/home/cptk37ymg_w1417156/.radiorubben-deploy/backups/homepage-followup-37773371431-1`. Fire opprinnelige filer er byteverifisert; to nye filer er registrert som tidligere fraværende.
- Offline tilbakeføring med tvungen feil bestod, og alle øvrige temafiler var uendret etter publisering.
- Minifisert stilark fikk ny cachegenerasjon og sidecache ble oppdatert via eksisterende WP-Optimize-metoder.
- Publisert desktop ved 1280 px: begge klubblogoene lastet, temperatur 38 px, hele timevarselet skjult, værboks 377,375 px. Nyhetssiden viste sidetittel 38,4 px og korttittel 21,76 px. Ingen WPVibe-utkastbanner.
- Publisert mobil ved 390 px: nyhetssiden viste sidetittel 30 px og korttitler 22 px; dokumentbredde375 px. Forsiden hadde dokumentbredde375 px, sidebar335 px og begge logo-elementene, uten horisontal overløp.
- Skjermbilder i lokalt OneDrive-arbeidsområde: `homepage-followup-published-20261008.jpg` og `news-followup-published-20261008.jpg`. Filene er ikke lagt i Git, og ekstern OneDrive-synk er ikke kontrollert.

| Temafil | Publisert rå SHA256 |
|---|---|
| assets/css/news-refinement.css | 917702f0830f220e5abc9fee8e711b5137d170c38619c20d5b9c72c13084aa1d |
| template-parts/home/sidebar-match.php | b949f6b72b729f4267fc7639d24ce3816d6a95551077238bb8009870a312bf83 |
| assets/css/homepage-refinement.css | ce1d0c97cc758b77cc002d494919540b228055ea93923b7e8d2b0112441c41c3 |
| template-parts/home/sidebar.php | 6449096fb4d5558870c0309a27a7003fcce9c476beb16881b997224e2199dde3 |
| front-page.php | 0cad895bc0caa1f44ccd85ffb84fbb8a42c9d802fc28043f48644a4fafca26ad |
| page-nyheter.php | 4c2b062762807313e9413e2de1d92ed9f053e5af75ff6cf055e795a90572e40b |

Den tidligere WPVibe-blokkeringen er historikk; denne autoriserte utgivelsen endret ikke vertens sikkerhetsfilter eller temaaktivering. Artikkelinhold og pluginfiler er bevart. Ingen main-merge.
