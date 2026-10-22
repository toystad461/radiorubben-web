# Weekly coverage and match cards — prepared, not deployed

Current-week preview 1398 was explicitly published through the normal WordPress gate and independently verified as public. It still uses its existing layout. Previous-week results have not yet been generated or published.

This change adds a separate completed-week recap (Monday 08:00 Europe/Oslo), while retaining Sunday 18:00 preparation of next week's fixtures. Both remain drafts until human approval. A bounded weekly endpoint enables manual recovery; matching existing articles are returned unchanged. Unconfirmed results remain visibly unresolved. Empty weeks and source failures are reported in the existing admin error area; there is no new delivery guarantee or automatic approval.

Match cards use charcoal backgrounds, rounded borders, readable team names, separate final scores, source links and smaller fixture metadata. Configured per-match Fotballdata/NFF crest URLs are captured with the facts; absent or failed logos leave names visible. No fabricated crests or score guesses. The existing AI notice stays outside the cards. CSS is scoped to cards and loaded only for weekly articles. Existing published article bodies are not automatically rewritten.

Unreviewed updates to live managed stories are rejected before the core write. A separate, source-bound correction can now be proposed for weekly/match stories, then approved through the existing review desk. Generic proposals currently use the article-corrections API; automatic rewrite remains player-only. Existing article URL and public copy remain unchanged until a valid proposal is approved. Test posts are excluded.

## Validation

Local PHP WASM 8.5: all 29 existing/new suites and syntax checks pass (67 PHP files). Synthetic negative checks cover missing publisher capability, changed weekly evidence, altered correction text, invalid dates, daylight saving transitions and logo-host rejection. These tests do not replace real WordPress staging checks or editorial approval.

The isolated card HTML was inspected in Chromium at 320, 390 and 1280 pixels, with no horizontal overflow. Both actual configured logos were fetched with HTTP 200; embedded copies make the preview self-contained. Theme integration and the authenticated production editing flow require post-deployment verification. JavaScript syntax checked.

## Deployment boundary and sequence

Do not deploy main or this entire branch. The adjacent JSON lists eight runtime files, their base hashes and candidate hashes; unchanged files must remain intact. Historical release scripts are untouched. The separate Studio image-contract fix must be included to restore the newsroom queue (currently a duplicate rrni_manifest fatal).

After explicit authorization: pin the reviewed source commits; audit current server hashes and stop on drift; stage the allowlisted runtime files, syntax-check with the server PHP, take a private code backup, install atomically under the existing deployment lock, and retain a receipt with before/after hashes. Restore that exact backup on failed health checks without overwriting later concurrent edits.

Verify front-end, authenticated newsroom queue and unchanged unrelated stories. Prepare results with POST /rr-fotballrobot/v1/weekly {kind:results,week:2026-10-12}; review the actual evidence and quality result before human publication. To update 1398, prepare and approve an article-corrections proposal against its exact current hash; preserve all 13 fixtures. Existing snapshot logos may be absent; no raw metadata bypass or silent source replacement. Verify actual public content, status, mobile cards and scheduled hooks after completion. Do not manually trigger notification jobs.
