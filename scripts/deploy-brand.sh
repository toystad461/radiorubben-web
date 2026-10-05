#!/usr/bin/env bash
set -Eeuo pipefail
mode=${1:?Mode required}
[[ "$mode" == brand-dry-run || "$mode" == brand-apply ]]
[[ ${GITHUB_REF:-} == refs/heads/main ]]
[[ ${GITHUB_RUN_ID:-} =~ ^[0-9]+$ && ${GITHUB_RUN_ATTEMPT:-} =~ ^[0-9]+$ ]]
host=cptk37ymg_w1417156@ssh.cptk37ymg.service.one
release="brand-${GITHUB_RUN_ID}-${GITHUB_RUN_ATTEMPT}"
stage=".radiorubben-deploy/staging/$release"
source_dir=wordpress/wp-content/themes/radio-rubben-wordpress-v1
ssh "$host" "umask 077; mkdir -p $stage/assets/css $stage/inc"
rsync -rltz "$source_dir/assets/brand/" "$host:$stage/assets/brand/"
rsync -rltz "$source_dir/assets/css/brand.css" "$host:$stage/assets/css/"
rsync -rltz "$source_dir/inc/brand.php" "$host:$stage/inc/"
rsync -rltz scripts/brand-logo.patch "$host:$stage/"
ssh "$host" bash -s -- "$release" "$mode" "${GITHUB_SHA:?}" < scripts/deploy-brand-remote.sh
