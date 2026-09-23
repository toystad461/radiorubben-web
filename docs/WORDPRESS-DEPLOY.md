# Radio Rubben: WordPress fra GitHub til webhotellet

## Status

Import av opplastet `radiorubben-kode-20260923.tar.gz`: 90 filer, aktivt tema
`radio-rubben-wordpress-v1` versjon 1.3.6 og fire egne plugins. Kildekopien
inneholder ikke database, uploads, wp-config.php, tredjepartsplugins eller studio.
Den statiske nettsiden i `dist/` er et separat prosjekt og publiseres ikke her.

Server: `ssh.cptk37ymg.service.one`, port 22.
Bruker: `cptk37ymg_w1417156`.
WordPress: `/run/webroots/r1417157`.
Studio uten www: `studio-public`; studio med www: `studio`. Sistnevnte har
standard DirectoryIndex, ingen videresending i den undersøkte .htaccess-filen.
Dette er et eget oppfølgingspunkt i webhotellets domeneoppsett.

## Før første publisering

1. Gå gjennom import-PR og PHP-kontroll. Kontrollen bruker runnerens PHP;
   kontroller også PHP 8.1-kompatibilitet på serveren før første publisering.
2. Opprett en egen ED25519-nøkkel på din Mac, uten å overskrive eksisterende nøkler.
   Installer bare den offentlige nøkkelen i serverkontoens authorized_keys.
   Bruk gjerne `restrict` foran nøkkelen. Privatnøkkelen skal aldri i Git.
3. Bekreft SSH-serverens fingeravtrykk med webhotellet. Lagre hele den bekreftede
   known_hosts-linjen, ikke bare SHA256-fingeravtrykket.
4. Under repository Settings → Secrets and variables → Actions legg til:
   - Secret `WP_DEPLOY_SSH_KEY`: den dedikerte private nøkkelen.
   - Secret `WP_DEPLOY_KNOWN_HOSTS`: bekreftet known_hosts-linje for SSH-verten.
   - Variable `WP_DEPLOY_ENABLED`: `true` når SSH-oppsettet er klart.
   - La `WP_DEPLOY_AUTO_APPLY` være tom eller `false` under innkjøring.
5. Slå sammen PR etter gjennomgang. Kjør workflow manuelt fra main med `dry-run`.
   Dette viser rsync-endringer uten å skrive til nettstedet.
6. Kontroller at kildekoden fortsatt samsvarer med live. Stopp samtidige WPVibe-
   endringer; hent fersk kode hvis nettstedet har blitt endret siden eksporten.
7. Ta en full backup med database og mediefiler før første reelle publisering.
   Oppbevar den privat, eksempelvis i OneDrive under Radio Rubben / IT & Utstyr /
   Backup. Denne PR-en oppretter ikke en OneDrive-backup.
8. Kjør manuelt `apply` fra main. Test innlogging, radioavspilling, quiz,
   avstemming og dashboard. Først deretter sett `WP_DEPLOY_AUTO_APPLY=true` for
   automatisk publisering ved relevante endringer på main.

Koble GitHub-nøkkelen kun til denne oppgaven. Rotér SSH-passordet som tidligere
ble delt i samtalen. GitHub-koblingen kan ikke legge inn Actions-hemmeligheter.

## Hva publiseres

Kun disse fem mappene under `wp-content`:

- themes/radio-rubben-wordpress-v1
- plugins/min-rubben
- plugins/RR_News
- plugins/RR_Quiz
- plugins/rr-radio-co-plugin-v1

Databasen, wp-config.php, uploads, studio og WPVibe-utkast/backup inngår ikke.
Ingen plugin aktiveres automatisk. Kode fra Code Snippets ligger i databasen og
inngår ikke i eksporten. Fjernede Git-filer slettes ikke automatisk fra serveren;
slettinger må vurderes separat. Unngå redigering direkte på server eller i WPVibe
etter at Git er hovedkilden, ellers kan neste publisering overskrive endringene.

## Backup og tilbakeføring

Apply krever curl, rsync, tar og WP-CLI på serveren. Skriptet kontrollerer
aktivt tema og home-adresse, tar en kodebackup utenfor webroten i
`~/.radiorubben-deploy/backups/<run-id>-<attempt>/code.tar.gz`, og bruker en lås
mot samtidige publiseringer. WordPress settes kort i vedlikeholdsmodus.

Ved feil etter at backup er tatt forsøker skriptet å gjenopprette de fem mappene
og avslutte vedlikeholdsmodus. HTTP-kontroll av forsiden og /wp-json/ avdekker
ikke alle funksjonsfeil. Nettverksbrudd eller avbrutt prosess kan kreve manuell
opprydding; løsningen er ikke en atomisk utrulling.

Ved manuell tilbakeføring: undersøk loggen, pakk backup ut i en privat mappe og
kopier bare de fem katalogene tilbake. `rsync --delete` brukes bare ved
rollback fra den komplette kodebackupen, for å fjerne filer utrullingen la til.
Kontroller nettstedet før en eventuell lås fjernes. Backuper og staging beholdes
for inspeksjon; følg med på diskplass og rydd eldre versjoner etter verifisering.

Databaseendringer fra kjørende plugins rulles ikke tilbake av kodeskriptet.
Ingen live-publisering eller SSH-test er utført ved opprettelsen av denne PR-en.
