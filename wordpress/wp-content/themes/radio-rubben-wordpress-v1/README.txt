RADIO RUBBEN ONE — v1.3.0
Designoppdatering til den komplette v1.2-pakken. 15. september 2026.

INSTALLASJON
1. Ta en sikkerhetskopi av nettstedet og gjeldende tema.
2. WordPress: Utseende > Temaer > Legg til nytt > Last opp tema.
3. Velg radio-rubben-one-v1.3-wordpress.zip.
4. Hvis WordPress finner samme tema, velg å erstatte den installerte versjonen.
5. Kontroller at Radio Rubben One er aktivt.
6. Kontroller strøm-URL under Utseende > Tilpass > Radio Rubben – radio og kontakt.
7. Tøm eventuell side-/optimaliseringscache slik at de nye stilene lastes.

Temaets mappe heter fortsatt radio-rubben-wordpress-v1. Oppdateringen
viderefører innstillingene for denne temamappen: logo, menyplasseringer,
kontaktfelt, CF7-shortcode, strøm-URL, fallback-tekst og LIVE-bryter.
Hvis installasjonen din bruker et annet mappenavn eller egne PHP/CSS-endringer,
må disse sammenlignes før erstatning.

NYTT
- Ny forside med «Ingen valg. Bare god radio.» og tydelig radiokort.
- Mørke flater, røde detaljer og roligere typografi uten eksterne fontkall.
- RR Reimagined, RR Gold, Discovery og lokal presentasjon.
- Aktuelt henter faktiske publiserte WordPress-innlegg med bilder og lenker.
- Eksisterende side-, artikkel-, arkiv-, søke- og kontaktmaler videreføres.
- Minispiller både på mobil og datamaskin.
- Manglende lydstrøm gir deaktivert avspilling; feil vises som tekst.
- Tastaturfokus, hopp-til-innhold, Escape for mobilmeny og redusert bevegelse.
- Oppdateringen endrer ikke valgte menyer, forside eller sideinnhold.

RADIO
Legg inn en offentlig HTTPS-lydstrøm i rr_stream_url-feltet.
LIVE NÅ er fortsatt manuelt styrt: slå bare på ved faktisk direktesending.
Uten LIVE-bryter vises AUTOMATIKK når en lydstrøm er konfigurert.
En konfigurert adresse beviser ikke at sendingen er tilgjengelig; feil vises
når lytteren forsøker å spille.
Artist/låt/neste er eksisterende manuelle fallback-felt, ikke en automatisk
Now Playing API. Sett dem til nøytral tekst hvis de ikke holdes oppdatert.
Det er ett audio-element per side. Avspilling fortsetter under scrolling,
men stopper ved vanlig navigering til en ny WordPress-side.

BEHOLDT
Custom Logo, favicon, kontaktinnstillinger og tidligere slugaliaser.
Contact Form 7 krever at pluginen og det valgte skjemaet finnes.
Adresse og organisasjonsnummer vises bare når de er konfigurert.
Sendeplan-/programtekst er redaksjonelt innhold, ikke en koblet radioplan.
Forsidens redaksjonelle faste tekst ligger i front-page.php, som tidligere.

KONTROLL
ZIP-struktur, lokale filhenvisninger, versjonsnummer og JavaScript er kontrollert.
Avspillerens manglende strøm, play/pause, volum og avspillingsfeil er testet
med simulerte nettleserobjekter. PHP/WordPress-runtime er ikke tilgjengelig
i kontrollmiljøet; PHP-malene må derfor også kontrolleres i WordPress.
Pakken er ikke installert eller kjørt i din WordPress-installasjon.
Kontroller etter opplasting: forside på mobil og PC, meny, ett innlegg,
kontakt, avspilling, pause, volum og eventuell LIVE-visning.

KILDE
WordPress Theme Handbook: https://developer.wordpress.org/themes/
