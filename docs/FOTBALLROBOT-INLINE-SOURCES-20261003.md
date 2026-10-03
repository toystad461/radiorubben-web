# Kildelenker i artikkelteksten

Fra 3. oktober 2026 ønsker Thomas kildehenvisning direkte i relevante setninger, for eksempel:

Breivik fikk også [gult kort i det 59. minutt](https://www.fotball.no/fotballdata/kamp/?fiksId=9007464).

## Bruk
- Nye kamp- og spillertekster får normalt 1–3 naturlige kildelenker når en konkret kontrollert kilde støtter setningen.
- AI-en foreslår tekstutdrag, avsnittsnummer og en eksisterende kilde-URL. Ingen HTML produseres av modellen.
- Lenker må stemme ordrett med teksten og finnes i bestemte kildefelt i faktapakken. Ukjente URL-er, usikre protokoller, gjettede ankre og tvetydige tekstutdrag avvises.
- Separat faktakontroll skal kontrollere at den tilknyttede kilden støtter akkurat opplysningen. Språkvask bevarer eller oppdaterer tekstutdragene.
- Endring av bare lenkemålet utløser også en ny faktakontroll. Ved kontroll av manuelt redigert HTML bevares lenkemålene i kontrollgrunnlaget.
- Systemet bygger og escaper WordPress-lenkene. Utdraget forblir ren tekst. Kildelisten nederst beholdes.
- Tom lenkeliste er gyldig når ingen entydig kildekobling finnes. Eldre artikler uten lenkedata støttes, men endres ikke.

## Omfang
Bare forfatteren, kvalitetsflyten, spillerens HTML-renderer og en felles lenkevalidator endres. Manuell sluttgodkjenning, bilderegler, kildedata, køer, varsler og tidsplaner består. Ingen omskriving eller publisering av eksisterende artikler inngår.

Promptrevisjon: `2026-10-03.2`. Eksisterende kvalitetsregelversjon beholdes slik at eldre redaksjonelle godkjenninger ikke nullstilles. Nye og endrede tekster kontrolleres med lenkevalidering og eksisterende godkjenningshash.

## Tester
`tests/inline-sources.php` dekker spiller- og kamp-HTML, ingress, tegnsett og escaping, kjent/ukjent kilde, tvetydige og overlappende utdrag, bevaring ved språkvask, ny faktakontroll etter lenkeendring, manuell HTML-kontroll og godkjenningshash. Kun simulerte kontrollsvar; ingen betalte AI-kall.

Endringen aktiveres separat fra tidligere umergede funksjonsgrener, med kontroll av gamle filhasher, sikkerhetskopi og tilbakeføring ved feil.
