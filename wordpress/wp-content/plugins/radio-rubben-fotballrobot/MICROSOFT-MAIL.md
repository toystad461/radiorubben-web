# Microsoft 365 e-post – 0.9.0

Innebygd, dedikert Microsoft Graph-transport for spillerreview og eksplisitte tilkoblingstester. Ingen ekstra pluginlisens. Eksisterende Microsoft 365-abonnement og Send As-rettighet for fotballrobot@radiorubben.no kreves. Kampkø, læring og FactStore fra 0.8.0 er bevart. Ikke en generell wp_mail-erstatning; andre nettsidemeldinger endres ikke.

## Oppsett

1. Installer pakken via WordPress → Utvidelser → Legg til → Last opp → erstatt gjeldende Fotballrobot.
2. Åpne Fotballrobot → Microsoft 365 e-post. Siden viser nøyaktig HTTPS-returadresse (admin-post.php?action=rrfr_microsoft_callback).
3. I Microsoft Entra → App registrations opprettes «Radio Rubben Fotballrobot», single tenant, plattform Web. Bruk returadressen fra WordPress. Ikke bruk SPA eller implicit flow.
4. Microsoft Graph / Delegated permissions: Mail.Send.Shared og offline_access. Ingen Application-permissions eller e-postlesetilgang. Standard User.Read trengs ikke av denne integrasjonen. Entra-administrator godkjenner nødvendige rettigheter dersom organisasjonens policy krever det.
5. Administrator oppretter klienthemmelighet under Certificates & secrets og limer Value direkte inn i WordPress sammen med tenant/client-ID. Ingen hemmeligheter i chat, Git eller dokumentasjon. Noter utløpsdato; forny før utløp.
6. Lagre, velg Koble til Microsoft 365, og logg inn med Thomas-kontoen som har Send As. Brukeren fullfører Microsoft-samtykket selv. Tilkoblingen ber om å sende e-post og fornye tilgang. Den trenger ikke å lese innboksen.
7. Send eksplisitt tilkoblingstest. Etter bekreftet mottak åpnes spillerforslag 1016 og «Send e-post på nytt». Ingen artikkel publiseres av e-posthandlinger.

## Kontrakt og sikkerhet

OAuth authorization-code flow med PKCE S256, single-tenant autorisasjons- og token-endepunkt. Tilfeldig state lagres som hash, bundet til WordPress-administrator, innloggingssesjon og konfigurasjonsgenerasjon; utløper etter ti minutter og forbrukes før tokenutveksling. Callback krever samme administratorinnlogging. Oppsett, tilkoblingsstart, frakobling og test krever administrator og POST-nonce. Ingen anonyme REST-ruter.

AES-256-GCM beskytter klienthemmelighet, tokens og midlertidig PKCE-verifier med nøkkel avledet fra WordPress auth-salt. Ikke-autoloadet option rrfr_microsoft_mail. Hemmeligheter vises ikke igjen. Saltrotasjon krever ny konfigurasjon og innlogging. WordPress-/serveradministratorer må fortsatt være betrodd.

En vedvarende option-lås beskytter refresh og konfigurasjonsendringer. Låsen fjernes i finally. Ved prosessavbrudd må administrator kontrollere at ingen operasjon kjører før rrfr_microsoft_lock slettes. Det finnes ingen automatisk låsovertakelse. Koble fra sletter lokale OAuth-/tilgangstokener; trekk også tilbake appens samtykke i Entra dersom tilgangen skal tilbakekalles hos Microsoft. Allerede påbegynt nettverkskall kan ikke trekkes tilbake ved frakobling.

Graph-kallet er POST /v1.0/me/sendMail med eksplisitt from fotballrobot@radiorubben.no og eneste mottaker thomas.sellevold-oystad@radiorubben.no. Sendt-kopi lagres hos innlogget konto med mindre Exchange-policy også kopierer til delt postkasse. Send As + Mail.Send.Shared er nødvendig; Full Access til delt postkasse er ikke nødvendig for /me-varianten. Den delegerte Microsoft-tillatelsen kan omfatte andre avsendere kontoen har rettigheter til; begrensningen til robotadressen håndheves i denne koden, ikke av scope alene.

Ingen HTTP-redirects, ingen automatisk send-retry og ingen fallback til PHP-mail. 202 betyr akseptert, ikke levert. Nettverksfeil/andre uklare svar krever kontroll av innboks/Sendt før manuell gjentakelse. Rå providerfeil, tokens, hemmeligheter og e-postinnhold logges ikke. Spillerreview lagrer en trygg feilmelding ved avvisning. Tilgang fornyes ved behov, men opphevet samtykke eller utløpt hemmelighet krever administratorhandling.

## Validering og utrulling

42 kontroller i tests/microsoft-mail.php dekker kryptering, manipulering, saltendring, endpoint-validering, state, sesjon, bruker, PKCE, replay, scopes, tokenrotasjon, faste adresser, feil, ingen retries, låsing og frakobling. Eksisterende godkjennings-, spiller-, lærings-, kampkø-, lagrings- og rapporttester består. Testene simulerer Microsoft og sender ingen e-post.

Reell Microsoft-registrering, tilkobling og levering er ikke testet. Pakken er ikke installert av denne oppgaven: nettleserverktøyets sikkerhetskontroll var utilgjengelig. Ingen GitHubpush. Eldre 0.8.0-pakke kan brukes til tilbakerulling; ny tilkobling bør først frakobles og samtykke trekkes tilbake i Entra ved avvikling. Ingen eksisterende artikler eller fakta slettes.

Referanser: https://learn.microsoft.com/en-us/graph/outlook-send-mail-from-other-user og https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-auth-code-flow
