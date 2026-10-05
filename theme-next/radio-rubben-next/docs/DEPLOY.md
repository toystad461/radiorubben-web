# Staging først – viktig migreringsgrense

## Leveransestatus

2.0.0-rc.1 er en installérbar, lokaltestet presentasjonskandidat. **Ikke aktiver direkte på radiorubben.no ennå.** Dagens produksjonstema 1.3.6 inneholder applikasjonskode som forsvinner fra kjøringen når temaet byttes. Ingen av disse gamle funksjonsfilene er lagt inn i den nye theme-ZIP-en. Det nye metadata-pluginet erstatter dem ikke.

Den komplette lokale kodekilden er fra 23. september. Den offentlige nettsiden og stilfilene er undersøkt 26. september. En eksisterende WPVibe-draft ble lest; den inneholder eldre header/forside enn dagens renderte nettside. Den er ikke endret eller publisert. Den lokale backupen/draften kan derfor ikke brukes som en garantert oppdatert kopi av kampmotorene.

## Bekreftede eierskap og avhengigheter

| Funksjon | Funnet i eksisterende kode | Før produksjonsbytte |
|---|---|---|
| Dagens Bremnesing, dashboard, neste kamp, speaker | `inc/bremnes-direkte-test.php` og tilknyttede `bremnes-*` / speaker-filer i gammel theme | Eksporter **dagens** kode og flytt til egen kamp-utvidelse; bevar ruter, option-nøkler, rettigheter, nonce, data og registrerte hooks. Ikke flytt 23. september-kopi over nyere produksjonskode. |
| RRLive | `rrlive-data.php` registrerer `rr_match`, `rrlive/kamp` og metadata | Flytt registrering/data-API til separat utvidelse med uendret route. Nye theme har bare maler og lesing. |
| Fotballrobot | Aktiv `radio-rubben-fotballrobot` 0.3.2, egen motor og artikkelfilter | Behold aktiv. Ny byline gjenkjenner eksplisitt `_rrfr_ai_match`; filteret får fortsatt `the_content`. Test ekte artikkel og faktakort på staging. |
| Min Rubben / Vipps | Separate plugins + `inc/member-hub.php`, `vipps-birthday.php`, `member-delete.php` i gammel theme | Flytt de theme-bundne kontofunksjonene separat; særlig innlogging, personvern, sletting og cache-unntak må verifiseres. Ny theme tolker ikke medlemsdata. |
| Quiz | `RR_Quiz` + theme `inc/quiz-controls.php`, `weekly-quiz.php` m.fl. | Behold plugins, flytt theme-avhengighetene og test synlighet/kortkoder. |
| Vær | Theme `inc/weather.php` + egne CSS/JS | Flytt innhenting/endepunkter til vær-utvidelse. Ny theme kaller eksisterende renderer kun hvis tilgjengelig. |
| Bømlo RSS | Aktiv `RR_News`; dagens forside har RSS-seksjon som ikke finnes i kildekopiens forside | Knytt eksisterende feed-cache/renderer til `rr_theme_feed_items` eller en eksplisitt visningshook. Ikke opprett en konkurrerende feedjobb. |
| Radio | Aktiv `rr-radio-co-plugin-v1`; native radio-UI og theme_mods | Behold radiointegrasjon. Verifiser riktig strøm/status, ingen dobbel spiller eller polling. |

Kildereferansene er read-only. Ingen produksjonsfiler, database, menytildeling, kategori eller permalinkinnstilling er endret av denne leveransen.

## URL-er og innhold

Registrerte kategorislugs bekreftet: `sport`, `fotball`, `bremnes-il`, `herrer`, `damer`, `leder`, `lokale_nyheter`, `programmer` m.fl. Temaet bruker WordPress-genererte lenker; ingen rewrite-regler registreres/flushes, og ingen kategorier opprettes. Vanlige post-/side-URL-er blir derfor bevart på databasenivå.

Spesialruter som `/dashboard/`, `/dashboard_test/`, `/dagenskamp/`, `/nestekamp/`, speaker-ruter og `/rrlive/kamp/...` er avhengige av eksisterende motor. Visuelle rammer alene gjør ikke disse rutene operative. Denne delen er ikke ende-til-ende-verifisert i den nye installasjonen.

## Utrulling

1. Ta en **fersk** backup av database, plugins, mu-plugins, aktiv theme, uploads og nødvendig rotkonfigurasjon. Verifiser gjenoppretting på staging. En eldre backup i notater er ikke en ny backup.
2. Opprett staging med produksjonens faktiske innhold, PHP-versjon, kortkoder, menyplasseringer, theme_mods, widgets og funksjonsutvidelser. Beskytt staging mot offentlig indeksering og eksterne utsendinger.
3. Eksporter/migrer dagens theme-bundne motorer til separate plugins. Bytt `get_template_directory*`-referanser til plugin-stier for motorfiler/assets. Flytt applikasjonsmaler med til eierpluginet; theme-shell kan kalles fra disse. Ikke aktiver kopierte motorer parallelt med de gamle (dobbel funksjonsregistrering/cron).
4. Last opp `radio-rubben-next-2.0.0-rc.1.zip` som nytt tema på staging. Valgfritt: aktiver `rr-editorial-contract-1.0.0-rc.1.zip`. Velg portrett i Tilpass, kontroller logo, menytildeling, kontakt- og strømdata. Ingen automatisk ny side/opprydding/migrering kjøres.
5. Sammenlign desktop og 320/390/768px: forside, artikkel (ordinær + robot), alle kategorinivåer, leder, arkiv side 2, søk med/uten treff, 404, program, kontakt og juridiske sider. Sammenlign forsidemoduler og eventuell eksisterende custom CSS.
6. Test innlogging/Vipps, anonym og autorisert tilgang til dashboard/speaker, kampskifte, Dagens Bremnesing, redigering/avstemning og historikk på **testdata**; ikke på en aktiv produksjonskamp. Test radio og RSS-cache i faktisk integrasjon. Sjekk at ingen hooks, cron-jobber eller tellere kjøres dobbelt.
7. Mål cache, bilder, ekstern trafikk og Core Web Vitals på staging med realistisk innhold. Tøm riktig cache først ved avtalt bytte. Produksjonsaktivering krever eget godkjent utrullingssteg etter at disse avhengighetene er lukket.

## Tilbakeføring

Behold gammel theme og fersk backup. Dersom nye motorplugins er aktivert, må disse koordineres med tilbakebytte slik at gammel theme ikke laster samme funksjoner samtidig. Gjenopprett kjent fungerende plugin-/theme-kombinasjon og eventuelle endrede innstillinger. Nye journalistmetadata trenger ikke slettes. Denne theme-kandidaten endrer ikke innhold ved aktivering/deaktivering.
