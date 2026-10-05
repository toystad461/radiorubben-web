# Mappestruktur og malbruk

| Sted | Ansvar |
|---|---|
| `theme.json` | Palett, systemfont, typografisk skala, radius, innholdsbredde, mellomrom |
| `functions.php`, `inc/setup.php` | Temaoppsett, menyer, presentasjonsinnstillinger, betinget innlasting |
| `inc/presentation.php` | Skrivebeskyttet datatilpasning, renderhjelpere, URL-oppslag |
| `inc/journalists.php` | Journalist-/forfattervisning uten lagring eller motor |
| `inc/compatibility.php` | Tidligere `rr_one_*` presentasjonshjelpere, ingen gamle applikasjonsfiler |
| `template-parts/site/` | Header, kampstripe, footer, minispiller |
| `template-parts/home/` | Forsidens hero, medlemsinvitasjon, siste saker, kildeflate, radio/om/samarbeid |
| `template-parts/content/` | Artikkelkort, arkiv og løkke |
| `template-parts/components/` | Kamp/live, radio/program, RSS, sponsor, kilde, byline og journalist |
| `patterns/` | Syv mønstre med standardblokker, kan redigeres uten utvidelser |
| `templates/` | Valgbare **PHP-sidemaler**, ikke HTML-blokkmaler |
| `assets/css/` | Eksisterende visuelle grunnlag, komponenter, editor, betinget forside/RRLive-CSS |
| `assets/js/ui.js` | Meny, avspillingskontroller og tidsvisning; ingen nettverks-/kampmotor |

## WordPress-maler

`front-page.php` bruker dagens Radio Rubben-layout når en statisk forside er valgt. Utseende → Tilpass → Radio Rubben – presentasjon → Forsidelayout kan endres til Sideinnhold / blokkmønstre. Temaet endrer aldri `show_on_front`, `page_on_front` eller `page_for_posts`. Dersom WordPress er satt til siste innlegg, brukes vanlig innleggsliste.

`page.php` viser eksisterende sideinnhold og kortkoder gjennom `the_content`. `single.php` viser byline, kategori, responsivt artikkelbilde/kreditering, innhold, kilde, journalist, tagger, navigasjon og kommentarer. Fotballrobotens eksisterende `the_content`-filter beholdes.

`archive.php`, `category.php`, `author.php`, `home.php` og `index.php` bruker WordPress sin hovedspørring og paginering. `search.php` og `404.php` deler tilgjengelig søkeskjema med unike felt-ID-er. Arkivet viser faktiske kategorilenker, uten hardkodede kategori-ID-er. Struktur Sport → Fotball → Bremnes IL → Herrer/Damer og Lederens ord endres ikke.

Eksisterende visuelle spesialmaler er beholdt for Lytt, Om, Reimagined, På Radio Rubben, Samarbeid og Kontakt, med legacyaliaser `about-us`, `contact-us`, `pages`, `pages-2` og `sponsor`. `page-nyheter.php` og `page-leder.php` har egne avgrensede, paginerte lister. `template-magazine.php` bevarer eldre sidemalvalg. Personvern/vilkår vises fra eksisterende sideinnhold, ikke fra det utdaterte juridiske utkastet i backupens PHP-mal; dette må sammenlignes på staging.

`page-rrlive.php` / `single-rr_match.php` gjenbruker RRLive-presentasjonen. De oppretter ikke `rr_match` eller nye ruter. Uten ekstern registrering av innholdstypen bruker RRLive-siden vanlig sideinnhold. Oversikten viser kun publiserte kamper. Kampdetaljer respekterer passordbeskyttelse, og manglende felt vises som ubekreftet. Rutene må fortsatt eies av eksisterende funksjonsutvidelse.

## Valgbare sidemaler

- Sport / Fotball / Bremnes: sideinnhold, hierarkisk kategorinavigasjon og eksternt levert kampkort.
- Journalistprofil: eksisterende side med `_rr_journalist_id`, profil og paginerte saker.
- Applikasjonsramme: full bredde for **allerede tilgangsbeskyttet** blokk/kortkode fra dashboard, Speakerboard eller Dagens kamp. Temaet er ingen adgangskontroll. Eksisterende `template_redirect`-motorer har fortsatt forrang.
- Radio / Program: innhold, lydblokker og sponsorplass.
- Lokalt / RSS: kildeinnhold og eksisterende feed-kortkoder. Ingen feed hentes av temaet.

Velg mal på eksisterende sider; ikke opprett erstatningssider med nye URL-er. Mønstrene er redaksjonelle utgangspunkt. Eksempeltekst må byttes før publisering. Kamp-/dashboardmønstrene har ingen aktive kontrollknapper.

## Child theme

`get_template_part()` og `get_theme_file_uri()` finner child-filer først. Intern bootstrap bruker parent-filer og unike funksjonsnavn. Overstyr eksempelvis `template-parts/components/match-card.php`, `assets/css/components.css`, `front-page.php` eller `theme.json`. Child `style.css` lastes etter foreldrestilene. Bruk komponentfiltre i egen utvidelse for data, ikke omdefinering av PHP-funksjoner.

Nytt navn betyr egne theme_mods. Temaet leser gamle `theme_mods_radio-rubben-wordpress-v1` som fallback for logo, menyplassering og radiovisning, uten å kopiere eller skrive dem. Eksplisitte nye verdier, også tom strøm-URL, vinner. Velg forsideportrett via Customizer; mediefilen importeres ikke automatisk. På en eksisterende installasjon med gamle theme_mods brukes tidligere portrettvedlegg 760 som read-only fallback dersom det fortsatt finnes som bilde. En eksplisitt ny verdi overstyrer dette; ferske installasjoner får ikke en hardkodet medieavhengighet.

## Gjenbrukbare RR-maler (05.10.2026)

Nye sider bruker `templates/content.php`, `application.php`, `editorial.php` eller `football.php`.
De deler ramme/overskrift/seksjonsnavigasjon under `template-parts/page/`; redaksjonell mal og `single.php` deler `template-parts/content/article.php`.
Fire nye patterns gir seksjoner, kort og kombinerte fotball-/funksjonssider. Se `../../docs/DESIGNSYSTEM-ARKITEKTUR.md` i repoet for forskningsgrunnlag, komponentregler, pluginavhengigheter, redaktørveiledning og stagingporter.
Dette er fortsatt hybrid/PHP-maler, ikke full Site Editor-/HTML-malstøtte.
