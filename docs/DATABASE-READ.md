# Les innhold fra WordPress-databasen

Arbeidsflyten `Les publisert innhold fra WordPress-databasen` henter gjeldende,
publiserte WordPress-sider og innlegg via SSH og WP-CLI. Den er skrivebeskyttet
og kjører bare når den startes manuelt fra `main`.

## Slik henter du uttrekket

1. Åpne repositoryet `toystad461/radiorubben-web` på GitHub.
2. Gå til **Actions** og velg **Les publisert innhold fra WordPress-databasen**.
3. Velg **Run workflow** med grenen `main`.
4. Når kjøringen er fullført, last ned artefakten
   `wordpress-publisert-innhold`. Den slettes automatisk etter én dag.

Uttrekket inneholder ID, type, tittel, slug, endret dato og innhold for
publiserte sider og innlegg. Passordbeskyttede elementer filtreres bort.
Arbeidsflyten leser data med WP-CLI på webhotellet og bruker databasekoblingen
som allerede ligger i WordPress-installasjonens lokale `wp-config.php`.
Databasepassordet legges derfor ikke i GitHub, arbeidsflytloggen eller repoet.

## Avgrensning

Dette er et innholdsuttrekk, ikke en full databasekopi. Det inneholder ikke
brukerkontoer, stemmer, kommentarer, innstillinger, kode-snippets, andre
databasetabeller eller mediefiler. Det skriver heller ikke tilbake til
WordPress eller lagrer uttrekket som en Git-commit.

En full SQL-eksport kan inneholde personopplysninger, passord-hasher,
sesjonsdata og plugin-data. Den må behandles som en privat backup og skal ikke
lastes opp til dette repositoryet eller som en vanlig GitHub Actions-artefakt.
