# Nå/neste fra offentlig radio – 01.11.2026 Europe/Oslo

Autorisert som neste steg etter at hjemmesidens play-knapp ble koblet til VPS.
Git-base main559a65c8; installert kilde ea62278b6003fb6991b7ea2c6d28f390ff18972b.
PR111 i WEB-repoet er draft; den må ikke forveksles med eldre Studio-PR111.

Bare to nye filer er installert i wp-content/mu-plugins:
- rr-public-radio.php SHA256 abc5cda7e19eb76d64d82ffc96cb9585a760ee774e22e8c6ab848b42908e1e88
- rr-public-radio.js SHA256 fc635cba91116ec7e08e7582f716888b2fb1c0d158de85f4019737b68f754b72

Førverdien for begge var fraværende; eksisterende filer ble ikke overskrevet.
Privat installasjonskvittering og kildekopi:
/home/cptk37ymg_w1417156/.radiorubben-deploy/backups/public-radio-q61i5jbz.
Målroten ble kontrollert som /customers/9/3/1/cptk37ymg/webroots/r1417157,
home=https://www.radiorubben.no, aktivt tema=radio-rubben-next.

Tillegget virker bare når den valgte strømmen er https://5.249.252.78/radio.mp3.
Det leser /radio-status.php uten cookies hvert tiende sekund mens siden er synlig.
Artist/tittel settes som tekst, aldri HTML. Foreldet status, frakobling og feil
fjerner nå/neste-påstanden. Gamle statiske neste-felt skjules gjennom lesefiltre;
databasen endres ikke. Temaets avspillingskontroller og lydtransport bevares.

Målrettede Node-tester og begge CI-workflows på installert commit besto.
PHP8.4-syntaks og WordPress-oppstart ble kontrollert på webhotellet.
Innlogget nettleser viste Jingle2 → AlanJackson med neste AaronPritchett,
og lydavspilling hadde readyState4 uten mediefeil. Anonym HTML skjuler gammel
Om litt-tekst; WP-Optimize-bundelen inneholder statusendepunktet og credentials:omit.
Offentlig JS svarte200. Studio-endepunktet publiserer bare schemaVersion,
generatedAt, playing, now og next; innslag inneholder artist/title/kind.

Tilbakeføring: flytt kun de to nye rr-public-radio-filene ut av mu-plugins til
den private backupmappen. Tøm eventuelt WP-Optimize sitt JavaScript-mellomlager
dersom eldre bundle beholdes. Ingen kø, musikk, database eller temafiler gjenopprettes.
Gamle statiske neste-felt vil igjen vises ved tilbakeføring; de er ikke endret her.

Begrensning: tekst følger serverens PCM-status; hver nettlesers lydbuffer kan gi
noen sekunders forskjell mellom tekstbytte og hørbar overgang. Ingen eksakt
synkronisering til lytterens lokale lydposisjon påstås.
