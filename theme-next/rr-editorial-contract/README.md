# Radio Rubben Editorial Contract 1.0.0-rc.1

Valgfri separat WordPress-utvidelse. Registrerer fire metafelter for innlegg og sider, redigeringsboks, sanitization, nonce, postbasert tilgangskontroll og REST-støtte. Lagrer identitet og kildedata uavhengig av theme. Ingen AI-kall, nye brukere, cron, nettverkskall, roller eller automatisk publisering. Deaktivering sletter ikke data.

Theme viser metadata også når denne utvidelsen ikke er aktiv. REST-skriving krever utvidelsen eller tilsvarende registrering i en annen funksjonsutvidelse. Publisering styres av vanlige WordPress-rettigheter; opprett en begrenset konto på staging, og bruk status=draft i VPS-integrasjonen. Ikke bruk administratorkonto for motorer.

Dette er IKKE en erstatning for RRLive, Dagens Bremnesing, Speakerboard, Min Rubben eller RSS-motoren. Før produksjonsbytte må eksisterende theme-bundne funksjoner flyttes til egne utvidelser og testes. Se theme/docs/DEPLOY.md.
