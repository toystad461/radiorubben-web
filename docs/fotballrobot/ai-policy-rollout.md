# Autorisert utrulling av AI-policy 1.0.0 – 2026-10-08

Eier ba eksplisitt om deploy av de tre policyendringene. Web utrulles separat
fra Studio, hvor en annen autorisert utrulling pågår.

Kodekilde: `054d0927464b36b20100e5b4082dda36c9af7390` (#30 etter merge av #73).
Fersk serveraudit 17:36 UTC: 40 pluginfiler. Den eneste runtimeendringen er
`includes/publication-gate.php`; README og historiske PR30-manifester røres ikke.
Pluginens versjonsfelt forblir 0.10.5; policykonstanten er 1.0.0.

`ai-policy-release.json` binder eksakte før/etter-hasher. Installasjonen krever
uendret plugin, privat backup, atomisk erstatning og produksjonens eksisterende
utrullingslås. Feil i etterkontroll tilbakefører bare egen endring. Testene dekker
produksjonsdrift, endret pakke, backup, installasjon, etterkontroll og tilbakeføring
med vern mot samtidige endringer. Ingen innholdsgenerering eller publiseringstest.

Gamle kvalitetskontroller uten policyversjon blir ugyldige for senere
publiseringsforsøk. De må kjøres på nytt; eksisterende artikler omskrives ikke.

Planlagt kontroll etter installasjon: WordPress laster policy 1.0.0, den kanoniske hjemmesiden `https://www.radiorubben.no/`
svarer HTTP 200, og samtlige 40 pluginhasher samsvarer med ettermanifestet.
Faktisk resultat dokumenteres separat etter fullført utrulling.

Første preflight stoppet før kodeendring fordi domenet uten www normalt gir 301.
Videresendingen er kontrollert; helsesjekken bruker nå kanonisk www-adresse.

## Faktisk resultat

Utrullingen er fullført. WordPress lastet policy 1.0.0, kanonisk hjemmeside
svarte HTTP 200, og en ny uavhengig audit bekreftet alle 40 etter-hasher.
Bare `includes/publication-gate.php` ble erstattet. Privat backup finnes på
`~/.radiorubben-deploy/backups/ai-policy-aa6232316967`.
Eksakt tidspunkt og kildeidentitet: `ai-policy-production-receipt.json`.
Ingen artikler er opprettet, endret eller publisert som del av denne utrullingen.
Ingen innlogget redaksjonell ende-til-ende-test er gjennomført i produksjon.
