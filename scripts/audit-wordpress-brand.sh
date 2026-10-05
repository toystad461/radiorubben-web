#!/usr/bin/env bash
set -Eeuo pipefail
root=/run/webroots/r1417157
wp=(/usr/local/bin/wp --path="$root" --skip-plugins --skip-themes)
echo 'BRAND AUDIT: active theme and stored logo settings (read-only)'
"${wp[@]}" option get stylesheet
"${wp[@]}" option get site_icon || true
"${wp[@]}" theme mod get custom_logo || true
echo 'BRAND AUDIT: matching old/new files and SHA-256'
find "$root/wp-content/uploads" "$root/wp-content/themes" -type f \
  \( -iname '*Hovedlogo*' -o -iname '*Liggende*' -o -iname '*Profilbilde*' \
     -o -iname '*Logo-hvit*' -o -iname '*Logo-svart*' -o -iname '*Uten-verdilinje*' -o -iname '*Ikon-*' -o -iname '*Logopakke*' \
     -o -iname '*RR_Logo*' -o -iname '*A1067EB6*' -o -iname '*36690609*' \
     -o -iname 'radio-rubben-logo.*' -o -iname 'site-icon.*' \) \
  -exec sha256sum {} +
