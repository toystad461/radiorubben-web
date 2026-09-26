# Radio Rubben Site Functions 1.0.0-rc.2

Separat migreringsutvidelse for funksjoner som tidligere ble lastet av `radio-rubben-wordpress-v1` 1.3.6. Basert på fersk, lesebasert produksjonskopi fra 26. september 2026, med filhashene dokumentert i `SOURCE-MANIFEST.json`.

## Hva følger med?

- Dagens Bremnesing, kampvalg, avstemning, kampklokke, NFF-import, speaker, velkomstmanus, kampsponsor, arkivering og neste kamp.
- `/dashboard/` med undermenyer og den eksisterende `/dashboard_test/`-prototypen fra aktiv Code Snippets #9.
- RRLive-innholdstype, metadata-/editorregistrering og eksisterende `/rrlive/kamp/...`-adresser.
- Ukentlig quiz, quiz av/på-kontroll, eksport/sletting av quizdata.
- Min Rubben-visning, profil, støtteflate og selvbetjent kontosletting.
- Værfunksjon med tilhørende ressurser.
- Tilpasning av eksisterende kampdata og RSS-renderer til Next-theme. Ingen ny RSS-jobb.

Dette er en samlet overgangspakke med moduler i `inc/`, ikke en ny implementering av alle motorene. Gamle funksjonsnavn, innstillingsnøkler, kamp-/stemmeidentitet, kontroller og nettadresser beholdes. Applikasjonsvisninger og tilhørende CSS/JS eies nå av pluginet. Det nye themet eier fortsatt nettstedets generelle layout og komponenter.

## Lasting og tilbakeføring

Pluginet venter til theme-funksjonene er lastet. Med gammelt hovedtheme eller dets child theme er det passivt (`legacy-theme-owns-functions`). Med Next overtar det funksjonene (`active`). Oppdages en annen kopi av sentrale funksjoner, stopper det innlasting med en administratormelding (`conflict`), fremfor å laste to motorer.

Det kjøres ingen aktiveringsmigrering, datanullstilling, ny cron-jobb eller sletting ved avinstallering. Ved tilbakebytte til gammelt theme blir broen passiv på neste forespørsel. Behold gammel theme og pluginpakker. Endringer i data gjort mens en bruker arbeider rulles ikke tilbake ved et theme-bytte.

**Ikke roter WordPress-saltene ved produksjonsbyttet.** Eksisterende stemmeidentiteter bruker dem. Staging har egne salter og egne testkontoer; det er ikke en oppskrift på å bytte produksjonens salter.

## Avhengigheter som fortsatt skal beholdes

Eksisterende `min-rubben`, `login-with-vipps`, `RR_Quiz`, `RR_News`, radio-plugin, Fotballrobot, Contact Form 7 og relevante Code Snippets skal beholdes. WPVibe gir fortsatt RRLive sin eksisterende felteditor. Pluginet erstatter ingen av disse tjenestene eller deres konfigurasjon.

Code Snippets #9 kan forbli aktiv for tilbakeføring: broens handler for `/dashboard_test/` kjører først og avslutter forespørselen når Next er aktivt. Med gammelt theme kjører originalen. Andre snippets (blant annet `/kamp` og kampvisning) beholdes. De ligger fortsatt i databasen og må være med i backup.

Bursdagsinnsamlingen som var pauset i dagens kode, forblir pauset. Pluginet aktiverer den ikke på nytt.

## Installering

Installer ZIP-en under **Utvidelser → Legg til ny**, på staging først. Den kan aktiveres sammen med gammelt theme uten å overta funksjoner. Bytt deretter til Next som del av den planlagte stagingovergangen. Ikke slett gamle theme-filer eller kopier den gamle testdatabasen over produksjon.

Eksisterende RRLive-regler beholdes. På en helt ny testinstallasjon må permalinkreglene oppdateres etter registreringen. Pluginet oppretter ikke nye sider, kategorier eller brukere. Bevar de eksisterende sidene og ID-ene, særlig medlems-/quizsidene 736 og 496.

## Videre utvikling

Dette sikrer eksisterende funksjoner før en større ombygging. Modulene kan senere skilles i egne tjenesteplugins eller VPS-motorer. Bevar først deres datakontrakter, eksisterende API/ruter og rettigheter. Ikke kopier motorene tilbake inn i theme.

Se leveransens `docs/MIGRERING-OG-STAGING.md` for faktiske tester og gjenstående eksterne sluttkontroller. Kandidaten er ikke aktivert på produksjon.
