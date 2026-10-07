# Flytting til VPS uten frontendbytte

1. Journalistmotorene flyttes fra egne WordPress-utvidelser til VPS. Temaet flyttes ikke.
2. VPS bruker autentisert WordPress REST API over HTTPS. En begrenset konto kan skrive egne utkast, ikke endre tema/utvidelser eller andre forfatteres saker. Hold legitimasjon i privat serverkonfigurasjon; aldri i JavaScript eller theme.json.
3. Opprett/oppdater vanlig WordPress-innhold med samme journalist-ID og kildefelter. Bevar eksisterende post-ID ved oppdatering, og ikke endre slug/permalink. Temaets artikkel-, byline- og arkivmaler fortsetter å bruke samme data.
4. La redaktøren godkjenne før publisering. Idempotens, faktasjekk, køer, tidsplan, logging, retries, driftsstatus og AI-kostnadskontroll eies av motor/API-utvidelsen.
5. For kamp/RSS er det den separate integrasjonsutvidelsen som mellomlagrer API-data og oversetter til komponentkontrakten. Temaet skal ikke gjøre et VPS-kall for hver sidevisning.

Eksempel på body til WordPress `POST /wp-json/wp/v2/posts` (ingen faktisk forespørsel er sendt):

```json
{
  "status": "draft",
  "title": "Kontrollert artikkeloverskrift",
  "content": "<p>Redaksjonelt gjennomgått tekst.</p>",
  "meta": {
    "_rr_journalist_id": "rr-fotball",
    "_rr_source_name": "NFF / Fotball.no",
    "_rr_source_url": "https://www.fotball.no/",
    "_rr_editor_name": ""
  }
}
```

Dette er en stabil presentasjonskontrakt, ikke en ferdig VPS-server. Test tilgang, dobbeltlevering, utkastbevaring, tidsavbrudd og oppdatering av eksisterende innlegg på staging før motorflytting. Utvidelsen som registrerer metadata må være aktiv. Ved deaktivering beholdes lagrede data og visning, men REST-skriving av disse feltene er ikke lenger registrert.
