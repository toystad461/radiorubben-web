# Historikk for publisert WordPress-innhold

Arbeidsflyten **Historikk for publisert WordPress-innhold** leser gjeldende,
publiserte sider og innlegg fra RadioRubben.no. Den kjører daglig kl. 03:17 UTC
og kan også startes manuelt fra `main` i GitHub Actions.

Ved endringer oppretter den én commit på grenen
[`wordpress-content-history`](https://github.com/toystad461/radiorubben-web/tree/wordpress-content-history).
Innholdet ligger i `pages/<WordPress-ID>.json` og `posts/<WordPress-ID>.json`.
Filnavnene forblir de samme når tittel eller adresse endres, slik at Git viser
endringene for hver side eller artikkel. Uendret innhold lager ingen ny commit.
Slettede eller avpubliserte elementer fjernes fra siste øyeblikksbilde, men
tidligere versjoner finnes fortsatt i grenens Git-historikk.

## Bruk

1. Åpne **Actions → Historikk for publisert WordPress-innhold**.
2. Velg **Run workflow** på `main` for en eksport med én gang.
3. Se filene og endringene på `wordpress-content-history`.
4. Et samlet JSON-uttrekk finnes også som artefakten
   `wordpress-publisert-innhold` på hver kjøring. Artefakten slettes etter ett
   døgn; Git-historikken blir værende.

Det private repositoryet inneholder nå en varig kopi av *publisert innhold*.
Kun ubeskyttede sider og innlegg lagres: ID, type, tittel, slug, sist endret
og WordPress-innhold. Brukere, stemmer, kommentarer, innstillinger, kode-snippets,
andre databasetabeller og mediefiler inngår ikke. Eksporten leser via WP-CLI på
webhotellet. Databasepassordet legges ikke i GitHub. Arbeidsflyten skriver
aldri tilbake til WordPress; gjenoppretting må gjøres kontrollert i WordPress.

Dette er historikk for publisert tekstinnhold, ikke en full databasebackup.
