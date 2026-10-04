# Player story image and correction fix

PlayerReview never set a featured image, unlike the match writer. New drafts
and rewrites now use the existing neutral Radio Rubben football illustration
(813) when no editor-selected image exists. An unavailable image stops writing
with an actionable message. Editor choices survive a rewrite. Match artwork
(812) is unchanged.

The player prompt emphasizes confirmed human context and removes a redundant
statistics paragraph that repeated a documented debut. Facts and independent
quality checks remain mandatory.

Published player stories can have a separately staged correction. Preparing it
runs the existing fact and language reviewers without modifying the public
version, its approval, URL, image or sending email. The authenticated review
page shows the complete proposal and requires an explicit POST to update the
same article. Permissions, nonce, proposal token, source revision, image and
facts must still match. The ordinary publication gate remains active. Failures
restore the previous text and approval. Private source facts and story copy do
not belong in this repository.

Release 0.10.2: isolated branch based on PR #50; only six runtime files may be
installed, with exact baseline fingerprints, full regression tests, private
backup and rollback. No merge, full-repo deploy, automatic article publication
or test email. The article image was independently corrected through WordPress
REST and verified published with featured_media 813.

Follow-up 0.10.3: surface pending corrections in the existing Studio queue and
delegate its explicit approval/rejection to the same protected methods. Seven
allowlisted runtime files, baseline 0.10.2. No Studio code or settings changed.
