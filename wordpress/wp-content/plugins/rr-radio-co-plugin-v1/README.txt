Radio Rubben Radio.co v1
========================

Dette er en enkel WordPress-plugin for Radio Rubben med:
- admin-innstillinger
- shortcode for spiller
- shortcode for nå spiller
- enkel Radio Rubben-styling

Installasjon
------------
1. Last opp ZIP-en i WordPress under Utseende eller Utvidelser > Legg til.
2. Aktiver pluginen.
3. Gå til Innstillinger > Radio Rubben Radio.co.
4. Legg inn:
   - Station ID
   - Stream URL
   - eventuelt standard coverbilde
5. Bruk shortcode på sider, innlegg eller widgets.

Shortcodes
----------
[rr_player]
[rr_now_playing]
[rr_listen_live_button]

Tips
----
- Station ID brukes mot public.radio.co-endepunktene.
- Stream URL er vanligvis i formatet https://streaming.radio.co/xxxxx/listen
- Nå spiller-data caches i noen minutter for å redusere API-kall.
