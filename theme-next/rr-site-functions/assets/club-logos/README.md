# Klubblogoer

Lokale presentasjonskopier fra https://www.fotballdata.no/logobank, hentet 26. september 2026 via HTTPS: `https://logo.fotballdata.no/logos/{klubb-ID}.jpg?w=200`.

Filnavnet er FIKS **klubb-ID**, ikke lag-ID. Logoene tilhører de respektive klubbene og følger ikke automatisk programkodens GPL-lisens. Brukes her for å identifisere lagene i kampomtalen.

827 Bremnes; 1716 Arna-Bjørnar; 808 Viggo; 805 Smørås; 814 Flaktveit; 1903 Galgen; 837 Fitjar; 795 Lyngbø; 1633 Åsane; 1681 Askøy; 3076 Stord; 3260 motstander fra eksisterende standardkamp.

`rr_site_club_logo_url()` oversetter kjente eksterne logo-URL-er til disse lokale filene ved visning. Lagringen av kampene endres ikke. Ukjente logoer bruker eksisterende URL; flere klubber må legges til når terminlisten utvides. Ingen eksterne kall eller automatiske nedlastinger skjer ved sidevisning.

Ved oppdatering: kontroller klubb-ID mot kampkilden, hent JPG over HTTPS, kontroller bildefilen og legg den inn i denne mappen. Installer ny pluginpakke. Temaet skal ikke eie logoarkivet.
