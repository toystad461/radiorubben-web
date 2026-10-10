# Historisk avstemningstest

Manuell import via FIKS-ID bruker Fotballdata `matches/{id}/people` og har ingen datogrense. Knappen for neste hjemmekamp filtrerer derimot bort tidligere datoer. Importen gjenbruker den ekte kampens lagrede speaker-/avstemningsstatus; en ferdig kamp blir derfor ikke en ny avstemning. `/dashboard_test/` bruker også den ekte avstemningen.

Den nye administratorruten `/avstemningstest/`, lenket fra kampoppsettet, henter historisk kamp og begge lagoppstillinger via eksisterende API-adapter. Bruk FIKS-ID, for eksempel 8985501, eller Fotball.no-lenken med `fiksId`. API-adapterens eksisterende hjemmekampavgrensning for Bremnes herrer/damer A gjelder fortsatt. Det lange kampnummeret på Fotball.no støttes ikke.

Hver administrator har én privat test under `rr_poll_simulation_user_{user}`. Import og «Ny test» nullstiller bare denne testen. Start, pause, andre omgang, innbytte, teststemme, stenging og avslutning følger eksisterende regler for spillerberettigelse og stenging etter 85 minutter. Ingen Vipps-identitet, premietrekning, live kampvalg, ekte stemmer, arkivskriving eller referatjobb benyttes. Historisk resultat vises bare dersom det finnes i det eksisterende kampens arkiv; mål/hendelser rekonstrueres ikke fra antakelser.

Alle handlinger krever administrator og nonce. Endringer har revisjonskontroll og atomisk forespørselslås. En avbrutt PHP-prosess kan etterlate låsen; da vises en blokkering, og administrator må undersøke før låsen slettes. Normal feil/retur frigjør låsen før redirect. Samtidige handlinger skal avvises, ikke slå sammen stemmer fra utdaterte sider.

Test: `php theme-next/tests/poll-simulation.php` og eksisterende `poll-fotballdata.php`. Kamp 8985501 brukes som identitet med syntetiske spillere; ingen produksjonsavstemning nullstilles. Produksjonskontrollen bekreftet at manuell import gjenbruker en åpnet kamps status. Endringen er ikke utrullet før separat godkjent deployment. Målhendelser og interaktiv nettleserverifikasjon mot produksjon inngår ikke i denne testen.
