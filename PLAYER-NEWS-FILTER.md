# Player news selection — Fotballrobot 0.9.9

Newly collected NFF data is not necessarily news. The former queue grouped all new observations by collection time; ordinary substitutions and renamed event labels could therefore generate articles and email.

The automatic NFF queue now selects before calling the writer:

| Observation | Automatic action |
| --- | --- |
| New goal, penalty goal, own goal or dismissal | One proposal per player and match, subject to verified context |
| Ordinary substitution, squad registration or yellow card | History only; substitutions/cards may accompany a qualifying goal or dismissal |
| Renamed/corrected event, aggregate statistics or club register change | History only |
| Missing identity, match date, competition, teams, result or confirmed final whistle | Wait for valid match evidence |
| Match older than 48 hours | History only |
| Match source older than six hours | Wait for refreshed evidence |

Context is parsed from the same NFF response as the player event. Final-whistle confirmation comes from the existing match observer; neither an elapsed kickoff nor a visible score proves completion. Each queued event must still match the latest snapshot and exact player/match/source identity. Discovery time does not make an old match fresh. Debuts, injuries and milestones are not inferred from substitutions or missing history.

The stable key is `match-news:{fiks_id}:{match_id}`. Existing review decisions and old timestamp-based drafts are preserved. A later collection time cannot create another proposal for the same player and match. Already handled legacy groups are skipped. Independently submitted, checked public news follows its existing workflow.

The writing prompt describes sporting events naturally and receives date, opponents, competition and score. Facts, language checks, explicit editorial evidence and manual final approval remain in place. Email uses the resulting proposal title and excerpt; it is not a separate AI prompt.

The monitor reports why observations are excluded. No observations, settings, source entries or existing articles are deleted or rewritten by this release. Tests cover routine-event suppression before writer/mail, match grouping, stale/incomplete evidence, identity conflicts, corrections, source parsing and existing publication gates.

Release uses a separate branch based on the active 0.9.8 revision. Only six runtime files are installed, after exact baseline verification, backup and maintenance mode. The new helper must not already exist. Postflight checks are read-only; no queue tick, test email or article publication is performed. Rollback restores the five previous files and removes only the newly installed helper.
