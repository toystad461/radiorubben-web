# Avgrenset publisering av Next-nyheter

Thomas har uttrykkelig bedt om å rette publiseringsveien og utføre publisering,
og har frafalt ny backup for denne avgrensede oppdateringen. Dagens Next er
aktivt; det skal ikke installeres eller aktiveres et annet tema.

## Bekreftet før utsending

GitHub-preflight 37681164715, kilde adf16bd7962fd33191031c60ecbecfb6be6a0d48,
besto mot den ekte produksjonsserveren. WP_DEPLOY_ENABLED=true og eksisterende
SSH-nøkkel/bekreftet vert fungerer. Environment wordpress-production og felles
concurrency radiorubben-wordpress beholdes; ingen permissions eller secrets er
endret og ingen godkjenningsgrenser omgås.

Aktivt stylesheet er radio-rubben-next; show_on_front=page. Frontfilens SHA-256:
4f727fe11d63e64974b92ecd5a94ebd1c2fec3612497d584ef59461a89ddbf13.
Dette matcher nøyaktig Git-versjonen i f7cce8c4129e68361e65e86928aca71c24140026.
news-priority.php og news-priority.css manglet som forventet. Kategorier og
underkategorier er kontrollert fra virkelig WordPress.

## Utsendelse

Kun tre filer fra testet source-commit 332e443349a79d225f7b659ee22e798a831ef7df.
Commit-push på den eksplisitt godkjente PR66-grenen starter denne engangsveien;
ingen main-merge eller utrulling av det gamle temaet brukes for å trigge den.
Ingen andre PR-er, plugins, innlegg, bilder, innlogging, Studio eller OneDrive
oppdateres. Ingen ny database-/filbackup opprettes. Eventuell tilbakeføring
bruker bare den hashidentiske frontfilen som allerede finnes i Git, ikke en ny
kopi av serveren. Ny PHP-mal og CSS legges først; frontfilen byttes sist med
atomisk rename. Før-hash og fravær av nye filer kontrolleres under samme
serverlås som tidligere WordPress-publiseringer. Andre temafiler kontrolleres
med hash før/etter, uten å kopieres. Eksisterende forsidelayout-valg beholdes.

Skriptet må stoppe ved avvik, eksisterende lås, vedlikehold eller manglende
autorisasjon. Før-hashen gjør veien engangsavgrenset; en ny push etter vellykket
publisering kan ikke overskrive ukontrollerte serverendringer. Reell WP_Query
kontrollerer nyhets-/sportskategorier før filbyttet. HTTP-readback verifiserer
seksjonene, radiospilleren og spillerwidgeten. Cache og offentlig visning uten
cache-parameter må også sjekkes før brukerlevering.

## Tester

De tre runtime-filene er uendret fra source-commitens tre grønne CI-kjøringer:
37675966368, 37675966215, 37675966082. PHP-kontraktene kjøres igjen i release.
Lokalt består shell-syntaks, PHP-syntaks og fire isolerte deployscenarioer:
normal installasjon, før-hash-avvik, kollisjon med en ny fil og tilbakeføring
ved HTTP-feil. Testene bruker midlertidig filsystem og stubbet WP/curl, ikke
produksjonsdata. Sluttresultat og etter-hasher dokumenteres på PR66 etter kjøring.

Status ved denne commit: publiseringsvei klar, faktisk resultat ennå ikke lest.
