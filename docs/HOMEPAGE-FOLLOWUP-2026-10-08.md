# Oppfølging av forside og nyhetsside – 8. oktober 2026

Kildeendring, ikke publisert.

- Forsidens neste kamp får lokale klubblogoer fra eksisterende kampdata. Manglende logo skjules; hjemme/borte og lagnavn beholdes.
- Værboksen komprimeres til stedsvalg, ikon, temperatur, kort beskrivelse og Yr-lenke. Stedssøk og kildehenvisning beholdes. Hele timevarselet skjules slik at ingen tom overskrift står igjen.
- Nyhetssidens sidetittel får 30–42 px og korttitlene 21–25 px, 22 px på mobil. Stilarket gjelder kun nyhetsoversikten.

GitHub er kildearkiv. Basert på publisert forsiderefinement fra PR68; ingen main-merge.

WPVibe-utkast: CSS ble lagret, men vertens sikkerhetsfilter avviste ny sidebar-match.php og enqueue-endringen i page-nyheter.php med HTTP403 Blocked, kl.11:29:29 og11:30:04 UTC. Lesing bekreftet at endringene manglet. Draft-sidebar ble derfor tilbakeført til eksisterende kampkomponent for å unngå feil. Ingen ny forespørsel for blokkerte endringer og ingen omgåelse ble forsøkt. Utkastets minifiserte CSS viste også gammel værpresentasjon, så visuell godkjenning er ikke ferdig.

Npm test og bygg bestått. GitHub-kontroller må også være grønne før eventuell utgivelse. Publisering krever ferdig forhåndsvisning, ferske serverhasher og tilbakeføringsbevis. Live-siden er uendret av denne oppfølgingen.
