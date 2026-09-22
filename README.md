# Radio Rubben web

Kildekode og versjonshistorikk for Radio Rubben-nettsiden og kommende tjenester som Dagens Bremnesing, dashboard, speakerverktøy og RRLive.

## Kom i gang

Krav: Node.js 22 eller nyere.

```bash
npm run check
npm run build
npm run dev
```

- `npm run check` kontrollerer at nødvendige nettsidefiler og grunnleggende HTML-innhold finnes.
- `npm run build` kontrollerer og klargjør den statiske nettsiden i `dist/`.
- `npm run dev` starter en lokal testserver.

## Arbeidsflyt

1. Lag en egen gren for endringen.
2. Gjør og test endringen lokalt.
3. Kjør `npm test` og `npm run build`.
4. Oppdater `CHANGELOG.md` ved synlige eller funksjonelle endringer.
5. Slå endringen sammen til `main` når den er godkjent.

Hemmeligheter, API-nøkler og passord skal aldri lagres i repositoryet.
