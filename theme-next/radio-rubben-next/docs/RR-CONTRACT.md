# rr-* kontrakt v1

## Grense

`rr-*` er en stabil offentlig tjeneste-/identitets-ID. Den er aldri et WordPress-brukernavn, en rolle/rettighet eller en API-hemmelighet. Temaets PHP-prefiks er `rr_theme_`, funksjonsutvidelsens `rr_editorial_`; CSS-komponenter beholder `rr-*`. Identiteter følger `^rr-[a-z0-9]+(?:-[a-z0-9]+)*$`.

Aktive visningsidentiteter er `rr-fotball` og `rr-lokal`. Temaet gir navn, rolle, beskrivelse og tydelig merking «Digital journalist». En menneskelig WordPress-forfatter brukes som fallback bare når ingen digital identitet er oppgitt. Ukjent eksplisitt ID vises som egen redaksjonell identitet, ikke som en person. Ingen redaktørgodkjenning eller faktakontroll hevdes automatisk.

Registrer flere profiler fra en funksjonsutvidelse:

```php
add_filter( 'rr_journalist_profiles', function ( $profiles ) {
    $profiles['rr-kultur'] = array(
        'name' => 'Kulturredaksjonen',
        'role' => 'Digital kulturjournalist',
        'type' => 'digital',
        'description' => 'Kulturlivet på Bømlo.',
        'avatar_url' => '',
        'url' => get_permalink( 123 ), // Eksisterende profilside, verifisert ID.
    );
    return $profiles;
} );
```

Profilbildet/lenken er valgfritt. Temaet oppretter ingen profilsider, brukere eller nye forfatter-URL-er. Bruk profilmalsiden og oppgi dens eksisterende URL via filteret. Profilens innleggsoversikt er basert på lagret identitet; gamle robotinnlegg støttes ved eksplisitt `_rrfr_ai_match`-markør. Ingen identitet gjettes fra kategorier.

## Varige metadata – eies av separat funksjonsutvidelse

| Felt | Betydning |
|---|---|
| `_rr_journalist_id` | Stabil journalist-ID, eksempel `rr-fotball` |
| `_rr_source_name` | Offentlig kildenavn |
| `_rr_source_url` | Offentlig originalkilde |
| `_rr_editor_name` | Bekreftet redaktørnavn, ellers tomt |

Alle er enkeltverdier av typen string for innlegg/sider. `rr-editorial-contract` registrerer feltene med sanitization, revisjonsstøtte og REST med `edit_post`-kontroll. Feltene er offentlig presentasjonsmetadata: legg aldri API-nøkler, interne noter eller sensitive kilder her. En redaktør kan fylle dem ut i egen metabox. Utvidelsen starter ingen AI, cron eller publisering. Datakilden er fortsatt ansvarlig for opphavsrett, kildegrunnlag og redaksjonell kontroll.

## Komponent-API

```php
rr_theme_component( 'match-card', array(
    'home' => 'Bremnes', 'away' => 'Viggo',
    'status' => 'finished', 'score' => array( 'home' => 2, 'away' => 2 ),
    'kickoff' => '2026-09-25T19:00:00+02:00',
    'venue' => 'ScaleAQ Stadion', 'url' => get_permalink( $match_post_id ),
) );
```

Datoer er ISO 8601 med tidssone. Status er `scheduled`, `live`, `finished` eller `postponed`. Manglende score skal være fraværende, ikke null som skal tolkes som 0. `0–0` er gyldig når begge verdier finnes. Temaet henter ikke kamper, beregner ikke stilling, velger ikke vinnere og starter ikke nedtelling av stemmegivning. En visuell nedtelling endrer aldri kampstatus når avsparkstid passeres.

Komponenter tar tekst/data, ikke vilkårlig HTML. Temaet escaper ved visning og avviser ukjente komponentnavn. Bilder bruker vedleggs-ID eller profilens URL; ingen HTML injiseres fra API. `rr_theme_rrlive_data` normaliserer eksisterende RRLive-felter og lister uten å skrive tilbake. Resultater, oppstillinger og hendelser bestemmes av motoren.

| Filter/hook | Data/bruk |
|---|---|
| `rr_journalist_profiles` | Map ID → name, role, type, description, avatar_url, url |
| `rr_theme_journalist_id` | Overstyr visnings-ID for et innlegg (ingen skriving) |
| `rr_theme_match_data` | Kampkortdata, kontekst `header`/`sport`/`card`, forespurt ID |
| `rr_theme_rrlive_data` | Eksisterende `rr_*` kampfelter for et `rr_match`-innlegg |
| `rr_theme_feed_items` | Inntil seks {title,url,source_name,published_at,excerpt}, kilde `bomlo-kommune` |
| `rr_theme_sponsors` | Inntil åtte {name,url,image_id,text} pr. plass `home-bottom`, `article-bottom`, `sport`, `radio` |
| `rr_theme_home_after_news` | Ekstern visuell blokk etter nyheter/kildeflate |
| `rr_theme_home_content` | Valgfri utvidelse av klassisk forside |

RSS-eieren henter, mellomlagrer, dedupliserer og krediterer kildene. Radio/stream-eieren leverer strøm/status/metadata. Temaets native audio-kontroller krever brukerklikk; ingen autoplay. Sponsoravtaler, målretting, klikksporing og betalingsdata ligger utenfor temaet. Redaksjonell metadata-plugin eier ikke kampmotoren.

## Reserverte navn

Se `rr-namespace.json`. `rr-redaksjon`, `rr-sport`, `rr-kultur`, `rr-musikk`, `rr-radio`, `rr-desk`, `rr-publisering`, `rr-feed`, `rr-live`, `rr-data`, `rr-foto`, `rr-some` og `rr-arkiv` er reservert, ikke kjørt eller registrert som brukere. `rr-human-*` reserveres for stabile menneskelige identiteter ved senere behov.
