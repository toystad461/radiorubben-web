# Unified player monitoring — 3 October 2026

The installed 0.9.4 plugin already followed five FIKS profiles and created reviewed proposals. Three separate ChatGPT watches still reported press/club news outside that workflow. Version 0.9.5 connects that research to the native draft queue and shows source receipts next to the player overview.

## Ownership

- WordPress Fotballrobot owns NFF collection, player identity, baseline/diffs, source storage, drafting, fact/language checks, review email and final approval.
- One consolidated external research task reads public press/club articles and delivers concise, checked facts through the private source endpoint. It is an input adapter, not a second publishing engine.
- All publication still requires Thomas's explicit approval of the current text and successful quality checks. Existing proposals, images, baselines, ignored observations and trash are not rewritten or restored.
- NFF collection remains one player per hourly cron in rotation (five profiles currently); the shared proposal queue handles at most one proposal per tick. WordPress cron depends on the site's scheduler. A daily press search is separate from this rotation.

## API

Administrator-authenticated WPVibe REST calls:

- `GET /rr-fotballrobot/v1/player-monitor`: engine/version, proposal setting, next cron, player health, stored news and associated proposal links.
- `GET /rr-fotballrobot/v1/players/{WP_PROFILE_ID}/sources`: all stored source receipts for this player.
- `POST /rr-fotballrobot/v1/players/{WP_PROFILE_ID}/sources`: queue a researched source, never publish or make an AI call during intake.

Required JSON: `fiks_id` (exact numeric identity), `url` (concrete public HTTPS article), `title` (<=200 characters), `identity_note` (<=400; public evidence identifying the player), `public_read:true`, `published_at` (ISO timestamp or YYYY-MM-DD when exact publication time is unknown), `checked_at` (ISO timestamp, within 48 hours), `event_date` (YYYY-MM-DD or null), and `facts` (1–6 short statements in original wording, <=350 characters each). Never submit unread paywalled text, copied articles, private family details, or instructions disguised as source facts. Intake records the authorized researcher's attestation; the server does not fetch arbitrary news URLs or independently certify their content.

Retries use normalized source URL + player profile; tracking parameters and fragments are removed. Duplicate requests return existing evidence/proposal, including failed jobs and trashed drafts. A timeout must be followed by a GET before retry. Other articles about an already covered event still require editorial deduplication by the research task. The plugin does not automatically infer that two different URLs tell the same story.

One draft at a time uses the existing Writer and PublicationGate. The oldest pending NFF/news item is selected. Failed generation is not retried automatically. New player drafts use the approved AI notice, WordPress paragraph blocks and direct source-domain links. No default Bremnes image is forced on other-club player stories.

## Players retained

| Player | WP profile | FIKS |
|---|---:|---:|
| Tiril Elisabeth Sellevold-Øystad | 1011 | 3942773 |
| Lasse Nathaniel Høgmo Breivik | 1012 | 3909887 |
| Sander Håvik Innvær | 1013 | 3584397 |
| Troy Engseth Nyhammer | 1014 | 3646624 |
| Anna Engeseth Lie | 1037 | 3909852 |

## Validation and release

Existing player approval tests extended for source authorization, exact player identity, public-reading attestation, source validation, stale research, duplicate/tracking URLs, draft-only queueing, unified queue order, failed-job handling, preservation of human edits/trash and protected routes. The entire existing PHP test suite remains required before deployment.

Selective release from verified 0.9.4 PHP hashes only, with code backup and rollback. Read-only live checks confirm the five profiles, source routes, existing proposal configuration and manual-approval mode. A fingerprint check verifies player state and existing proposal text/meta are unchanged. No live test email or fabricated news item is created.

Deployment and consolidation results will be appended after verification.
