# Radio Rubben – bildeopphav og AI-merking

Valgfri separat WordPress-utvidelse, versjon 1.0.0. Installert inaktivt
08.10.2026 fra `341f72045aa8a9df7bee34e3a3bdbcc742a9cdba` etter eksplisitt
produksjonsbestilling. Alle tre kjørefiler er kontrollert med SHA-256 og PHP-lint.
Eksisterende utvidelser er bevart. Aktivering avventer dokumentert opphav og
redaksjonell vurdering av standardbilder, blant annet bilde 1079. Virkelig
theme/CDN-visning er derfor ennå ikke verifisert. Basert på ren main, uten
avhengighet til åpne theme-/robotgrener.
Den følger identisk AI-policy 1.0.0 fra policyarbeidet.

## Bruk

I Mediebiblioteket → Rediger bilde → Radio Rubben – bildeopphav oppgir redaktøren
opphav, generator/modell (eventuelt uttrykkelig Ukjent), produksjonsdato,
kilde/rettighetsgrunnlag og hva som eventuelt er AI-redigert. Redaktøren kontrollerer
bildet og godkjenner. Lagre endringer i bildetekst/alternativ tekst først, og godkjenn deretter opphavet. Ukjent opphav kan lagres, men ikke godkjennes.

AI-genererte bilder får «AI-generert illustrasjon». Delvis generativt redigerte
bilder får «AI-redigert bilde». Fotos og vanlige illustrasjoner får ingen falsk
AI-påstand. Klassifisering er en menneskelig vurdering, ingen automatisk detektor.

Godkjenningen binder alle tilgjengelige original-/variantfiler med SHA-256,
metadata, bildetekst, alternativ tekst, policyversjon, redaktør-ID og tidspunkt. Endringer krever ny godkjenning;
historikk og begrunnelse bevares. Rettelse fra AI til annet opphav krever særskilt
begrunnelse. Metadata i et ugodkjent mellomstadium kan ikke fjerne kjent AI-merking.

## Dekning

- Bildeopphav og godkjenning redigeres i eksisterende mediebibliotek, med nonce,
  posttilgang, publiseringsrettighet og kontroll av revisjon før lagring.
- Publisering/oppdatering av innlegg og sider validerer hovedbilde, eksplisitte
  gallerier og inline HTML-bilder. REST returnerer 409 før lagring; klassisk
  publisering beholder innlegget som utkast med feilmelding. Senere tildeling av
  ugodkjent hovedbilde til publiserte/planlagte innlegg avvises.
- Merkingen vises over bildene ved WordPress' attachment-image-rendering og i
  artikkel-/feedinnhold, og inngår i attachment-caption. AI-opphav følger
  med i read-only `rr_media_policy` på REST-medieobjektet. Intern kilde-/rettighets-
  referanse, redaktøridentitet og historikk sendes ikke i dette offentlige feltet.
- Eksterne inlinebilder, byttede srcset-filer, picture-markup og gallerier uten
  eksplisitte ID-er avvises ved publisering. Importer og godkjenn bildene først.

## Avgrensning og utrulling

Ingen eksisterende bilder klassifiseres ved gjetning. Ingen vedlegg, innlegg eller
innstillinger endres ved aktivering. Før aktivering må redaksjonen klassifisere
bilder som brukes ved neste publisering, særlig Studios standardbilde ID 1079.
Eksisterende artikler blir ikke masseoppdatert. Registrerte bilder som blir ugyldige vises som «Bildet venter på ny kontroll» i støttet bildevisning; ugodkjent bildetekst holdes tilbake. Senere publiseringsforsøk med
ukjent bildeopphav blir sperret. Manuelt lagrede, aktive artikler blir ikke trukket
tilbake av en bakgrunnsjobb ved filendring.

Vilkårlig temakode, CSS-bakgrunner, eksterne bildebyggere og sosiale tjenester som
henter bare bildefilens URL ligger utenfor standard WordPress-bildeflyt. De må
kontrolleres separat før AI-bilder brukes der. Dette verktøyet brenner ikke
merking inn i bildefilen og kan ikke garantere at tredjepart beholder bildeteksten.
Optimole er aktiv i produksjon; virkelig theme/CDN-visning må kontrolleres etter
autorisert staging/utrulling. Testene bruker faktisk WordPress 7.1.3 i isolert MySQL,
PHP 8.2 og syntetiske bilder. Ingen produksjonsdata brukes.

Dokumentasjon for kontrollpunktene:
https://developer.wordpress.org/reference/hooks/wp_insert_post_data/
https://developer.wordpress.org/reference/classes/wp_rest_posts_controller/handle_featured_media/
