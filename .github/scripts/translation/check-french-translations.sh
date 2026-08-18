#!/usr/bin/env bash
set -euo pipefail

# Ensure French translations stay 100% complete (fail on untranslated or fuzzy entries).

exit_code=0

for po_file in centreon/lang/fr_FR.UTF-8/LC_MESSAGES/*.po; do
  statistics="$(LC_ALL=C msgfmt --statistics -o /dev/null "${po_file}" 2>&1)"
  untranslated_count="$(grep -oE '[0-9]+ untranslated' <<< "${statistics}" | grep -oE '[0-9]+' || echo 0)"
  fuzzy_count="$(grep -oE '[0-9]+ fuzzy' <<< "${statistics}" | grep -oE '[0-9]+' || echo 0)"

  if (( untranslated_count > 0 || fuzzy_count > 0 )); then
    exit_code=1
    echo "::error file=${po_file}::${untranslated_count} untranslated and ${fuzzy_count} fuzzy strings in ${po_file}"
    echo "Incomplete entries in ${po_file}:"
    msgattrib --untranslated "${po_file}"
    msgattrib --only-fuzzy "${po_file}"
  else
    echo "${po_file} is fully translated"
  fi
done

if (( exit_code != 0 )); then
  echo "::error::French translations must stay 100% complete. If your pull request does not have the update-translations label, add it to synchronize the po files automatically, then translate the remaining strings in centreon/lang/fr_FR.UTF-8/LC_MESSAGES/."
fi

exit "${exit_code}"
