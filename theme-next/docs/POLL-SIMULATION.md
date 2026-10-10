# Historisk avstemningstest

Manuell import via FIKS-ID bruker Fotballdata `matches/{id}/people` og har ingen datogrense. Knappen for neste hjemmekamp filtrerer derimot bort tidligere datoer. Importen gjenbruker den ekte kampens lagrede speaker-/avstemningsstatus; en ferdig kamp blir derfor ikke en ny avstemning. `/dashboard_test/` bruker også den ekte avstemningen.

Den nye administratorruten `/avstemningstest/`, lenket fra kampoppsettet, henter historisk kamp og begge lagoppstillinger via eksisterende API-adapter. Bruk FIKS-ID, for eksempel 8985501, eller Fotball.no-lenken med `fiksId`. API-adapterens eksisterende hjemmekampavgrensning for Bremnes herrer/damer A gjelder fortsatt. Det lange kampnummeret på Fotball.no støttes ikke.

Hver administrator har én privat test under `rr_poll_simulation_user_{user}`. Import og «Ny test» nullstiller bare denne testen. Start, pause, andre omgang, innbytte, teststemme, stenging og avslutning følger eksisterende regler for spillerberettigelse og stenging etter 85 minutter. Ingen Vipps-identitet, premietrekning, live kampvalg, ekte stemmer, arkivskriving eller referatjobb benyttes. Historisk resultat vises bare dersom det finnes i det eksisterende kampens arkiv; mål/hendelser rekonstrueres ikke fra antakelser.

Alle handlinger krever administrator og nonce. Endringer har revisjonskontroll og atomisk forespørselslås. En avbrutt PHP-prosess kan etterlate låsen; da vises en blokkering, og administrator må undersøke før låsen slettes. Normal feil/retur frigjør låsen før redirect. Samtidige handlinger skal avvises, ikke slå sammen stemmer fra utdaterte sider.

Test: `php theme-next/tests/poll-simulation.php` og eksisterende `poll-fotballdata.php`. Kamp 8985501 brukes som identitet med syntetiske spillere; ingen produksjonsavstemning nullstilles. Produksjonskontrollen bekreftet at manuell import gjenbruker en åpnet kamps status. Endringen er ikke utrullet før separat godkjent deployment. Målhendelser og interaktiv nettleserverifikasjon mot produksjon inngår ikke i denne testen.

## Klargjort utrulling

Workflow `deploy-simulation.yml` kjører tester og prøvekjøring av den avgrensede oppdateringen. Kun `inc/poll-simulation.php` legges til; importlenken og modullisten oppdateres med eksakte tekstendringer som bevarer produksjonens ekstra `match-day-embed` og andre lokale endringer. Privat backup, kontroll av samtidige filendringer og automatisk tilbakeføring ved feil inngår. Historiske deployskript brukes ikke til installasjonen.

Prøvekjøringen henter 8985501 fra API-et og starter/stemmer i minnet. Den lagrer ingen teststemmer eller endringer i ekte kamp. Etter installasjon kontrolleres aktiv modul, identiske ekte kamp-/avstemningsdata, anonym innloggingsbeskyttelse og frisk forside. `apply` tillates bare fra main. Produksjonsdata og personnavn skrives ikke i rapportene.

## Manuell godkjenningstest etter utrulling

1. Logg inn som administrator og gå til kampoppsett → «Hent en tidligere kamp og start simulert avstemning», eller `/avstemningstest/`.
2. Importer `8985501`, kontroller Bremnes–Flaktveit 2, kampdato og lagoppstilling. Gjenta med hele FIKS-lenken.
3. Start kampen og legg til en teststemme for en starter. Reserve skal avvises inntil «Simuler innbytte» brukes.
4. Trykk Pause → Start andre omgang → Gå til 85. minutt. Nye stemmer skal avvises. Avslutt testkampen.
5. «Ny test med samme kamp» skal gi tom avstemning og ny kampstart. Last samme test i to faner: handling fra gammel fane skal avvises når revisjonen er endret.
6. Ugyldig ID eller kildefeil skal vise feil og beholde forrige test. Ekte avstemning, valgt kamp, arkiv og artikkel for 8985501 skal være uendret.
7. Utlogget besøk skal gå til innlogging. Vanlig medlem skal få avslag. Ingen ekte Vipps-stemme eller publiseringshandling skal utføres under testen.
