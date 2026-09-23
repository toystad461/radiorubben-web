#!/usr/bin/env bash
# Run on the GitHub runner; authentication is configured by the workflow.
set -Eeuo pipefail
mode=${1:-dry-run}
[[ "$mode" == dry-run || "$mode" == apply ]] || exit 2
host=cptk37ymg_w1417156@ssh.cptk37ymg.service.one
root=/run/webroots/r1417157/wp-content
source_dir=wordpress/wp-content
paths=(themes/radio-rubben-wordpress-v1 plugins/min-rubben plugins/RR_News plugins/RR_Quiz plugins/rr-radio-co-plugin-v1)
for path in "${paths[@]}"; do
  [[ -d "$source_dir/$path" && ! -L "$source_dir/$path" ]] || exit 3
done
if [[ "$mode" == dry-run ]]; then
  for path in "${paths[@]}"; do
    rsync -rltz --dry-run --itemize-changes "$source_dir/$path/" "$host:$root/$path/"
  done
  exit
fi
[[ ${GITHUB_REF:-} == refs/heads/main ]] || { echo 'Apply requires main'; exit 4; }
[[ ${GITHUB_RUN_ID:-} =~ ^[0-9]+$ && ${GITHUB_RUN_ATTEMPT:-} =~ ^[0-9]+$ ]] || exit 5
release="${GITHUB_RUN_ID}-${GITHUB_RUN_ATTEMPT}"
# The remote script creates a private backup, locks deployment, then applies staged code.
ssh "$host" "umask 077; mkdir -p .radiorubben-deploy/staging/$release"
rsync -rltz "$source_dir/" "$host:.radiorubben-deploy/staging/$release/"
ssh "$host" bash -s -- "$release" < scripts/deploy-wordpress-remote.sh
