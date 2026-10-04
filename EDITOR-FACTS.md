# Explicit editor facts in player revisions

Style requests previously carried no new evidence, so editor-supplied facts could be omitted by the writer. Player reviews now offer a separate plain-text source field. Submitted facts retain the authenticated editor's name, user ID and timestamp in the source packet and history. The writer can cite those fields internally without presenting them as NFF data.

Adding facts invalidates the previous quality result. Rewriting still runs the existing fact and language checks, and the draft still requires manual publication approval. Capabilities, stale-form checks and silent-review preferences remain enforced. Ordinary style comments are not promoted to sources.

This branch depends on `fix/player-profile-and-match-followup`. Its selective release upgrades four runtime files from verified 0.9.7 fingerprints to 0.9.8 with a backup and rollback; it does not deploy other branch changes. The one-off requested revision reads the editor's existing WordPress comment using an exact hash, keeping private source prose out of Git and action logs. It refuses changed drafts or previously added evidence and never publishes or sends mail.

Validation covers authorization, stale forms, plain-text bounds, provenance, quality invalidation, retry preservation and manual approval, alongside the existing review desk and writer quality tests.
