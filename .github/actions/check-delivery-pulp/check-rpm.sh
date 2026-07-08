#!/usr/bin/env bash
# Verify that the delivered/promoted RPM packages are (1) physically present as
# content units in their target pulp repository and (2) resolvable through the
# published repodata (and actually fetchable from the content url).
#
# Source of the expected list: the manifest emitted by the delivery/promote step
# (primary); a workspace glob of *.rpm using the repository properties (fallback,
# delivery only). The script never exits early — see check-common.sh.
set -uo pipefail
shopt -s nullglob

# shellcheck source=.github/actions/check-delivery-pulp/check-common.sh
source "$(dirname "$0")/check-common.sh"

# --- expected package list -------------------------------------------------
if [[ -n "${MANIFEST:-}" && -s "${MANIFEST:-}" ]]; then
  echo "[INFO] Reading expected RPM packages from manifest $MANIFEST"
  PACKAGES_JSON=$(jq -c '.packages' "$MANIFEST")
elif [[ "${CHECK_MODE:-delivery}" == "delivery" ]]; then
  echo "[WARN] No manifest available, falling back to workspace *.rpm files"
  entries=()
  for FILE in *.rpm noarch/*.rpm x86_64/*.rpm; do
    [[ -e "$FILE" ]] || continue
    fn=$(basename "$FILE")
    arch=$(echo "$fn" | grep -oP '(x86_64|noarch)' | head -1)
    base=${fn%.rpm}; base=${base%."$arch"}
    release=${base##*-}; base=${base%-*}; version=${base##*-}; name=${base%-*}
    entries+=("$(jq -cn \
      --arg filename "$fn" --arg name "$name" --arg version "$version" \
      --arg release "$release" --arg arch "$arch" \
      --arg repository "$REPOSITORY_PREFIX-$arch" --arg base_path "$BASE_PATH_PREFIX/$arch" \
      '{filename:$filename,name:$name,version:$version,release:$release,arch:$arch,repository:$repository,base_path:$base_path}')")
  done
  if ((${#entries[@]})); then PACKAGES_JSON=$(printf '%s\n' "${entries[@]}" | jq -s '.'); else PACKAGES_JSON='[]'; fi
else
  echo "::error::check-delivery-pulp (promote) requires a manifest from the promote step"
  exit 1
fi

COUNT=$(echo "$PACKAGES_JSON" | jq 'length')
if [[ "$COUNT" -eq 0 ]]; then
  echo "::error::No expected RPM package to verify"
  exit 1
fi
echo "[INFO] $COUNT expected RPM package(s) to verify"

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
declare -A HREF_IDX      # idx -> published location href
for i in "${!E_FILENAME[@]}"; do META_IDX[$i]=false; done

deadline=$(( SECONDS + METADATA_TIMEOUT ))
while :; do
  declare -A PRIMARY_CACHE=()
  all_resolved=true

  for i in "${!E_FILENAME[@]}"; do
    [[ "${META_IDX[$i]}" == "true" ]] && continue
    base_path=${E_BASEPATH[$i]}

    if [[ -z "${PRIMARY_CACHE[$base_path]+set}" ]]; then
      repomd=$(curl -fsSL "$PULP_CONTENT_URL/$base_path/repodata/repomd.xml" 2>/dev/null || true)
      primary_href=$(printf '%s' "$repomd" | grep -oP '<location href="\K[^"]+primary\.xml[^"]*' | head -1 || true)
      if [[ -n "$primary_href" ]]; then
        PRIMARY_CACHE[$base_path]=$(curl -fsSL "$PULP_CONTENT_URL/$base_path/$primary_href" 2>/dev/null | gunzip -c 2>/dev/null || true)
      else
        PRIMARY_CACHE[$base_path]=""
      fi
    fi

    # the published href whose basename matches the expected filename
    href=$(printf '%s' "${PRIMARY_CACHE[$base_path]}" \
      | grep -oP '<location[^>]*href="\K[^"]+' \
      | awk -F/ -v f="${E_FILENAME[$i]}" '$NF==f {print; exit}')
    if [[ -n "$href" ]]; then
      META_IDX[$i]=true
      HREF_IDX[$i]=$href
    else
      all_resolved=false
    fi
  done

  [[ "$all_resolved" == "true" ]] && break
  [[ "$SECONDS" -ge "$deadline" ]] && { echo "[WARN] Metadata resolution timed out after ${METADATA_TIMEOUT}s"; break; }
  echo "[INFO] Waiting ${METADATA_INTERVAL}s for repodata to publish..."
  sleep "$METADATA_INTERVAL"
done

# --- fetchability + row accounting -----------------------------------------
for i in "${!E_FILENAME[@]}"; do
  fetchable=false
  if [[ "${META_IDX[$i]}" == "true" ]]; then
    url="$PULP_CONTENT_URL/${E_BASEPATH[$i]}/${HREF_IDX[$i]}"
    code=$(curl -fsSL -o /dev/null -w '%{http_code}' -I "$url" 2>/dev/null || echo 000)
    [[ "$code" == "200" ]] && fetchable=true
  fi
  record_row "${E_FILENAME[$i]}" "${E_ARCH[$i]}" "${PRESENT_IDX[$i]}" "${META_IDX[$i]}" "$fetchable"
done

render_summary
