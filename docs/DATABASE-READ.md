# Les publisert innhold fra WordPress-databasen

Arbeidsflyten `Les publisert innhold fra WordPress-databasen` gir et
skrivebeskyttet uttrekk av gjeldende, publiserte WordPress-sider og innlegg.
Den kjøres manuelt fra `main` og laster resultatet opp som en privat GitHub
Actions-artefakt som slettes etter ett døgn.

## Slik henter du uttrekket

1. Åpne repositoryet `toystad461/radiorubben-web` på GitHub.
2. Gå til **Actions** og velg **Les publisert innhold fra WordPress-databasen**.
3. Velg **Run workflow** med grenen `main`.
4. Når kjøringen er fullført, last ned artefakten
   `wordpress-publisert-innhold`.

Uttrekket inneholder ID, type, tittel, slug, endret dato og innhold for
publiserte sider og innlegg. Passordbeskyttede elementer filtreres bort.
Arbeidsflyten bruker WP-CLI på webhotellet og lar WordPress-installasjonen
bruke sin lokale databasekobling. Databasepassordet legges ikke i GitHub,
arbeidsflytloggen eller repositoryet.

## Avgrensning

Uttrekket er ikke en full databasekopi. Det inneholder ikke brukerkontoer,
stemmer, kommentarer, innstillinger, kode-snippets, andre databasetabeller
eller mediefiler. Det skriver ikke tilbake til WordPress og lagrer ikke
uttrekket som en Git-commit.

En full SQL-eksport kan inneholde personopplysninger, passord-hasher,
sesjonsdata og plugin-data. Den skal behandles som en privat backup og ikke
lastes opp til repositoryet eller som en vanlig GitHub Actions-artefakt.
