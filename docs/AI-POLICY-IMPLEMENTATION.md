# AI-policy – bildemerking i separat utvidelse

Denne PR-en legger policy v1.0 og arbeidsregler på ren main og innfører
`wordpress/wp-content/plugins/rr-media-policy`. Den trekker ikke inn de åpne
Fotballrobot-/theme-kjedene. Eksisterende runtime for Fotballrobotens policygate er
levert separat i #73/#74 og endres ikke av denne PR-en.

Implementert: mediebibliotekfelt, dokumentert opphav, fil-/varianthasher,
menneskelig versjonsbundet godkjenning, rettelseshistorikk, synlig AI-merking i
standard WordPress-bildevisning, read-only REST-kontrakt og kontroller før
publisering. Se pluginens README for presist omfang, tester og utrulling.

Ikke utført: aktivering, klassifisering av eksisterende bilder, ekte
WordPress/theme/CDN-kontroll i produksjon og bildedistribusjon til sosiale medier.
Hørbar lydmerking utvikles i separat Studio-PR. Publikumsversjonen av policyen
på «Om oss» er ikke publisert. Ingen deploy eller innholdspublisering her.
