# Oppfølging av forside og nyhetsside – 8. oktober 2026

Kildeendring, ikke publisert.

- Forsidens neste kamp får lokale klubblogoer fra eksisterende kampdata. Manglende logo skjules; hjemme/borte og lagnavn beholdes.
- Værboksen komprimeres til stedsvalg, ikon, temperatur, kort beskrivelse og Yr-lenke. Stedssøk og kildehenvisning beholdes. Hele timevarselet skjules slik at ingen tom overskrift står igjen.
- Nyhetssidens sidetittel får 30–42 px og korttitlene 21–25 px, 22 px på mobil. Stilarket gjelder kun nyhetsoversikten.

GitHub er kildearkiv. Basert på publisert forsiderefinement fra PR68; ingen main-merge.

WPVibe-utkast: CSS ble lagret, men vertens sikkerhetsfilter avviste ny sidebar-match.php og enqueue-endringen i page-nyheter.php med HTTP403 Blocked, kl.11:29:29 og11:30:04 UTC. Lesing bekreftet at endringene manglet. Draft-sidebar ble derfor tilbakeført til eksisterende kampkomponent for å unngå feil. Ingen ny forespørsel for blokkerte endringer og ingen omgåelse ble forsøkt. Utkastets minifiserte CSS viste også gammel værpresentasjon, så visuell godkjenning er ikke ferdig.

Npm test og bygg bestått. GitHub-kontroller må også være grønne før eventuell utgivelse. Publisering krever ferdig forhåndsvisning, ferske serverhasher og tilbakeføringsbevis. Live-siden er uendret av denne oppfølgingen.

## Autorisert publiseringsvei

Brukeren godkjente direkte GitHub/SSH-publisering med «gjør det». Lokal nettleserkontroll med eksempeldata viste begge faktiske klubblogoene, temperatur 38 px, skjult timevarsel og sidetittel 38,4 px/korttittel 21,76 px ved 1280 px. Ved mobilbredde 390 px var sidetittel 30 px/korttittel 22 px og dokumentbredden 375 px uten horisontal overløp. Dette er CSS-kontroll med eksempeldata, ikke en full lokal WordPress-kopi. Lastrekkefølge i WordPress er sikret med stilarkavhengighet.

Utgivelsen er låst til seks temafiler fra kildecommit 90f88666533684165813513fe4b7a01bc021fb7c. Serveren sammenlignes med PR68-grunnlaget 2b4779d; rå SHA256 registreres og backup verifiseres. Alle øvrige temafiler kontrolleres uendret. Tilbakeføring testes i et midlertidig filsystem før SSH-nøkler lastes. Cache oppdateres via WP-Optimize sine eksisterende metoder; sikkerhetsfilteret endres ikke.
