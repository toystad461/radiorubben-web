# Match-driven player follow-up

Selective release: Fotballrobot 0.9.7 and player widget 1.3.0, based on live Fotballrobot 0.9.6 and widget 1.2.0. The widget tree is imported from PR45; this branch builds on PR46. Do not broadly deploy or merge stacked branches without reconciliation.

- Exact approved player, club and registered team IDs are retained. Torbjørn Kallevåg is player 2039307, Hødd team 171, club 1730. His parser failure came from historical rows with only a card link and zero appearances.
- Widget cron interval: 60 seconds. NFF match polling starts 75 minutes before scheduled kickoff and remains at 60 seconds until confirmed finish, with a three-hour active window. Far-future match details are checked every six hours. One response serves every followed player in a match.
- Team schedules: hourly, ten minutes around kickoff, two due teams per tick. Moved or cancelled fixtures invalidate old roles/streams. Scores alone do not mean a fixture is finished.
- MyGame: a separate, lower-priority 30-minute check. No speculative stream URLs.
- Playing state requires the correct fixture, lineup and recorded match event. Start time alone never proves a player is on the field. Exact FIKS IDs identify both sides of substitutions and dismissals. An unreported match remains unknown. Live/squad status expires after 150 seconds without successful refresh.
- Confirmed finished games leave the widget. Corrections are checked every five minutes for 30 minutes, then hourly within the six-hour schedule window. Profile statistics receive priority after finish.
- Browser polling uses a public, read-only, no-store REST response every minute while visible. It preserves collapse and scroll position and avoids replacing a focused widget. Rendering never calls NFF/MyGame.
- Robot observations share fetched HTML and collect events during a match; only a finished packet enters the existing hourly editorial queue. No per-minute article generation. Debut claims still require profile/tournament evidence and editorial review.
- Profile background queue runs one due profile every five minutes, normally on a six-hour rotation. Existing hourly editorial/email workflow is unchanged. Explicit draft creation can disable notification while retaining all quality and manual-publication gates.

## Hosting limitation

The current one.com SSH environment reports `CRONTAB_UNAVAILABLE`. WordPress cron needs a request to trigger due work. An open widget makes one request each minute, but uninterrupted unattended polling needs an external minute scheduler. No external paid service has been created or selected. Render is connected but reports no selected workspace; account selection requires the user.

Upstream delay is separate from polling frequency. No exact one-minute freshness guarantee is made. A run has a 40-second work budget and 12 match requests; oldest due active matches come first. Errors remove active labels and remain visible in the private cache.

## Release checks

Regression tests cover card-only profile rows, wrong IDs, conflicting team rows, exact substitutions, dismissal, finished and stale states, 75-minute scheduling, deduplication across players, interval suppression, and silent drafts retaining publication gates. Browser tests cover minute refresh and mobile state preservation. Deployment checks exact baseline/target fingerprints, backs up only changed files, uses maintenance during replacement, requires a completion marker, refreshes Torbjørn through the owning plugin and verifies 12 public players.

The debut script re-reads exact match 8989882 and player 3942773, requires final 0–3, the 90th-minute entry event and one career tournament appearance before creating an idempotent, silent, quality-reviewed draft. Publication remains manual.

## Production verification, 4 October 2026

Release commit `8083fe324521ac208d9304dfb096b75d5a71134e`, successful Action `37213226425`. All 234 PHP assertions and browser checks passed. Selective deployment completed at 15:31 UTC. Postflight verified 12 approved players, Torbjørn's Hødd team/club, the 60-second widget event and 300-second profile queue. Public REST readback showed Torbjørn's Hødd–Egersund fixture on 11 October at 17:00 Oslo, NFF 8986801, in the sorted strip. No unwanted unknown-squad/no-fixture notices.

Article 1091, “Tiril debuterte i 2. divisjon for Brann 2”, is a draft with completed quality review and no email sent. Categories: Sport, Fotball, Damer. It links directly to the verified NFF match and profile. Manual approval remains required.

## External scheduler ready for workspace selection

`scripts/player-minute-render.json` contains the proposed Render create arguments, without a workspace ID. `scripts/player-minute-pulse.mjs` triggers the existing WordPress cron URL, then checks the public widget heartbeat; it reports HTTP failures and a heartbeat older than five minutes. No credentials or added dependencies. Auto-deploy is disabled. Render's published minimum is $1/month per cron job; actual compute is charged by active seconds (https://render.com/docs/cronjobs).

Not created: Render exposes two workspaces and explicitly requires the user to confirm which workspace to use. Do not infer/select a workspace from account ownership. Once confirmed, inspect existing services to avoid duplicate cron jobs, create or update only the intended service, then verify at least two scheduled runs and fresh WordPress heartbeat. The pulse does not guarantee upstream NFF freshness.
