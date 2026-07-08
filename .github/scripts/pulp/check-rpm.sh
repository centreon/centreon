#!/usr/bin/env bash
# Verify that the delivered/promoted RPM packages are (1) physically present as
# content units in their target pulp repository and (2) resolvable through the
# published repodata (and actually fetchable from the content url).
#
# The expected list is the manifest emitted by the delivery/promote step. The
# script never exits early — see check-common.sh.
set -uo pipefail

# shellcheck source=.github/scripts/pulp/check-common.sh
source "$(dirname "$0")/check-common.sh"

load_expected "RPM"

# --- load expected packages into parallel arrays ---------------------------
mapfile -t E_FILENAME   < <(echo "$PACKAGES_JSON" | jq -r '.[].filename')
mapfile -t E_ARCH       < <(echo "$PACKAGES_JSON" | jq -r '.[].arch')
mapfile -t E_REPOSITORY < <(echo "$PACKAGES_JSON" | jq -r '.[].repository')
mapfile -t E_BASEPATH   < <(echo "$PACKAGES_JSON" | jq -r '.[].base_path')

# --- physical presence: content units in each repository's latest version --
declare -A PRESENT_BY_REPO
declare -A EXPECTED_COUNT_BY_REPO

for repo in $(printf '%s\n' "${E_REPOSITORY[@]}" | sort -u); do
  version_href=$(pulp rpm repository show --name "$repo" 2>/dev/null | jq -r '.latest_version_href // empty')
  if [[ -z "$version_href" ]]; then
    echo "[WARN] Repository $repo does not exist or has no version"
    PRESENT_BY_REPO[$repo]=""
    continue
  fi
  PRESENT_BY_REPO[$repo]=$(
    curl -fsSL -H "Authorization: Github $PULP_TOKEN" -G \
      --data-urlencode "repository_version=$version_href" \
      --data-urlencode "pulp_label_select=module=$MODULE_NAME" \
      --data-urlencode "limit=1000" \
      "$PULP_URL/api/v3/content/rpm/packages/" 2>/dev/null \
      | jq -r '.results[].location_href' | awk -F/ '{print $NF}'
  )
done

declare -A PRESENT_IDX   # idx -> true|false
for i in "${!E_FILENAME[@]}"; do
  repo=${E_REPOSITORY[$i]}
  EXPECTED_COUNT_BY_REPO[$repo]=$(( ${EXPECTED_COUNT_BY_REPO[$repo]:-0} + 1 ))
  if printf '%s\n' "${PRESENT_BY_REPO[$repo]:-}" | grep -Fxq "${E_FILENAME[$i]}"; then
    PRESENT_IDX[$i]=true
  else
    PRESENT_IDX[$i]=false
  fi
done

# right number: flag content-unit filenames present under the module that were
# not expected (a count/content mismatch, e.g. a stale package left behind)
for repo in "${!EXPECTED_COUNT_BY_REPO[@]}"; do
  present_count=$(printf '%s\n' "${PRESENT_BY_REPO[$repo]:-}" | grep -c . || true)
  if [[ "$present_count" -gt "${EXPECTED_COUNT_BY_REPO[$repo]}" ]]; then
    echo "::warning::Repository $repo holds $present_count module packages but only ${EXPECTED_COUNT_BY_REPO[$repo]} were expected (unexpected extras)"
    while read -r fn; do
      [[ -z "$fn" ]] && continue
      if ! printf '%s\n' "${E_FILENAME[@]}" | grep -Fxq "$fn"; then
        record_row "$fn" "?" "false" "false" "false"
      fi
    done < <(printf '%s\n' "${PRESENT_BY_REPO[$repo]:-}")
  fi
done

# --- metadata resolvability + fetchability, with a bounded retry window -----
declare -A META_IDX      # idx -> true|false
declare -A RESOLVED_IDX  # idx -> published location href
for i in "${!E_FILENAME[@]}"; do META_IDX[$i]=false; done

# one resolution round: fetch each base_path's primary.xml once, then match the
# published href whose basename equals the expected filename
resolve_pending() {
  local -A primary_cache=()
  local all_resolved=true i base_path repomd primary_href href
  for i in "${!E_FILENAME[@]}"; do
    [[ "${META_IDX[$i]}" == "true" ]] && continue
    base_path=${E_BASEPATH[$i]}

    if [[ -z "${primary_cache[$base_path]+set}" ]]; then
      repomd=$(curl -fsSL "$PULP_CONTENT_URL/$base_path/repodata/repomd.xml" 2>/dev/null || true)
      primary_href=$(printf '%s' "$repomd" | grep -oP '<location href="\K[^"]+primary\.xml[^"]*' | head -1 || true)
      if [[ -n "$primary_href" ]]; then
        primary_cache[$base_path]=$(curl -fsSL "$PULP_CONTENT_URL/$base_path/$primary_href" 2>/dev/null | gunzip -c 2>/dev/null || true)
      else
        primary_cache[$base_path]=""
      fi
    fi

    href=$(printf '%s' "${primary_cache[$base_path]}" \
      | grep -oP '<location[^>]*href="\K[^"]+' \
      | awk -F/ -v f="${E_FILENAME[$i]}" '$NF==f {print; exit}')
    if [[ -n "$href" ]]; then
      META_IDX[$i]=true
      RESOLVED_IDX[$i]=$href
    else
      all_resolved=false
    fi
  done
  [[ "$all_resolved" == "true" ]]
}

wait_for_metadata
check_fetchable_and_record
render_summary
