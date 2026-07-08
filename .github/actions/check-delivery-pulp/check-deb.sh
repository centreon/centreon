#!/usr/bin/env bash
# Verify that the delivered/promoted DEB packages are (1) physically present as
# content units in their target pulp repository and (2) resolvable through the
# published apt metadata (and actually fetchable from the content url).
#
# Metadata resolution mirrors promote-deb.sh: the structured publication serves
# packages under the canonical pool layout, so the published Filename is resolved
# from the suite's Packages index by sha256. Architecture "all" packages are
# listed under every binary-<arch>, so they are looked up across the suite's
# architectures. The script never exits early — see check-common.sh.
set -uo pipefail
shopt -s nullglob

# shellcheck source=.github/actions/check-delivery-pulp/check-common.sh
source "$(dirname "$0")/check-common.sh"

# --- expected package list -------------------------------------------------
if [[ -n "${MANIFEST:-}" && -s "${MANIFEST:-}" ]]; then
  echo "[INFO] Reading expected DEB packages from manifest $MANIFEST"
  PACKAGES_JSON=$(jq -c '.packages' "$MANIFEST")
elif [[ "${CHECK_MODE:-delivery}" == "delivery" ]]; then
  echo "[WARN] No manifest available, falling back to workspace *.deb files"
  entries=()
  for FILE in *.deb; do
    [[ -e "$FILE" ]] || continue
    name=$(dpkg-deb -f "$FILE" Package)
    version=$(dpkg-deb -f "$FILE" Version)
    arch=$(dpkg-deb -f "$FILE" Architecture)
    sha256=$(sha256sum "$FILE" | cut -d' ' -f1)
    entries+=("$(jq -cn \
      --arg filename "$FILE" --arg name "$name" --arg version "$version" --arg arch "$arch" \
      --arg sha256 "$sha256" --arg repository "$REPOSITORY_NAME" --arg base_path "$BASE_PATH" \
      --arg suite "$SUITE" --arg relative_path "$POOL_PATH/$FILE" \
      '{filename:$filename,name:$name,version:$version,arch:$arch,sha256:$sha256,repository:$repository,base_path:$base_path,suite:$suite,relative_path:$relative_path}')")
  done
  if ((${#entries[@]})); then PACKAGES_JSON=$(printf '%s\n' "${entries[@]}" | jq -s '.'); else PACKAGES_JSON='[]'; fi
else
  echo "::error::check-delivery-pulp (promote) requires a manifest from the promote step"
  exit 1
fi

COUNT=$(echo "$PACKAGES_JSON" | jq 'length')
if [[ "$COUNT" -eq 0 ]]; then
  echo "::error::No expected DEB package to verify"
  exit 1
fi
echo "[INFO] $COUNT expected DEB package(s) to verify"

mapfile -t E_FILENAME   < <(echo "$PACKAGES_JSON" | jq -r '.[].filename')
mapfile -t E_ARCH       < <(echo "$PACKAGES_JSON" | jq -r '.[].arch')
mapfile -t E_SHA256     < <(echo "$PACKAGES_JSON" | jq -r '.[].sha256')
mapfile -t E_REPOSITORY < <(echo "$PACKAGES_JSON" | jq -r '.[].repository')
mapfile -t E_BASEPATH   < <(echo "$PACKAGES_JSON" | jq -r '.[].base_path')
mapfile -t E_SUITE      < <(echo "$PACKAGES_JSON" | jq -r '.[].suite')
mapfile -t E_RELPATH    < <(echo "$PACKAGES_JSON" | jq -r '.[].relative_path')

# --- physical presence: content units in the repository's latest version ----
declare -A PRESENT_BY_REPO
for repo in $(printf '%s\n' "${E_REPOSITORY[@]}" | sort -u); do
  version_href=$(pulp deb repository show --name "$repo" 2>/dev/null | jq -r '.latest_version_href // empty')
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
      "$PULP_URL/api/v3/content/deb/packages/" 2>/dev/null \
      | jq -r '.results[].relative_path'
  )
done

declare -A PRESENT_IDX
for i in "${!E_FILENAME[@]}"; do
  if printf '%s\n' "${PRESENT_BY_REPO[${E_REPOSITORY[$i]}]:-}" | grep -Fxq "${E_RELPATH[$i]}"; then
    PRESENT_IDX[$i]=true
  else
    PRESENT_IDX[$i]=false
  fi
done

# --- metadata resolvability + fetchability, with a bounded retry window -----
# resolve a package's published Filename from a suite Packages index by sha256
resolve_filename() {
  local packages=$1 sha=$2
  printf '%s' "$packages" | awk -v sha="$sha" '
    BEGIN { RS = ""; FS = "\n" }
    index($0, "SHA256: " sha) {
      for (i = 1; i <= NF; i++) if ($i ~ /^Filename: /) { sub(/^Filename: /, "", $i); print $i; exit }
    }'
}

declare -A META_IDX
declare -A FILE_IDX
for i in "${!E_FILENAME[@]}"; do META_IDX[$i]=false; done

deadline=$(( SECONDS + METADATA_TIMEOUT ))
while :; do
  declare -A PKG_CACHE=()   # key: base_path|suite|arch -> Packages body
  declare -A ARCHES_CACHE=() # key: base_path|suite -> space separated arches
  all_resolved=true

  for i in "${!E_FILENAME[@]}"; do
    [[ "${META_IDX[$i]}" == "true" ]] && continue
    base_path=${E_BASEPATH[$i]}; suite=${E_SUITE[$i]}; arch=${E_ARCH[$i]}

    # architectures to search: the package arch, plus every arch of the suite
    # for "all" packages (which are duplicated across each binary-<arch>)
    search_arches="$arch"
    if [[ "$arch" == "all" ]]; then
      sk="$base_path|$suite"
      if [[ -z "${ARCHES_CACHE[$sk]+set}" ]]; then
        ARCHES_CACHE[$sk]=$(curl -fsSL "$PULP_CONTENT_URL/$base_path/dists/$suite/Release" 2>/dev/null \
          | awk -F': ' '/^Architectures:/ { print $2; exit }')
      fi
      search_arches="${ARCHES_CACHE[$sk]:-amd64 arm64 all}"
    fi

    filename=""
    for a in $search_arches; do
      ck="$base_path|$suite|$a"
      if [[ -z "${PKG_CACHE[$ck]+set}" ]]; then
        PKG_CACHE[$ck]=$(curl -fsSL "$PULP_CONTENT_URL/$base_path/dists/$suite/main/binary-$a/Packages" 2>/dev/null || true)
      fi
      filename=$(resolve_filename "${PKG_CACHE[$ck]}" "${E_SHA256[$i]}")
      [[ -n "$filename" ]] && break
    done

    if [[ -n "$filename" ]]; then
      META_IDX[$i]=true
      FILE_IDX[$i]=$filename
    else
      all_resolved=false
    fi
  done

  [[ "$all_resolved" == "true" ]] && break
  [[ "$SECONDS" -ge "$deadline" ]] && { echo "[WARN] Metadata resolution timed out after ${METADATA_TIMEOUT}s"; break; }
  echo "[INFO] Waiting ${METADATA_INTERVAL}s for apt metadata to publish..."
  sleep "$METADATA_INTERVAL"
done

# --- fetchability + row accounting -----------------------------------------
for i in "${!E_FILENAME[@]}"; do
  fetchable=false
  if [[ "${META_IDX[$i]}" == "true" ]]; then
    url="$PULP_CONTENT_URL/${E_BASEPATH[$i]}/${FILE_IDX[$i]}"
    code=$(curl -fsSL -o /dev/null -w '%{http_code}' -I "$url" 2>/dev/null || echo 000)
    [[ "$code" == "200" ]] && fetchable=true
  fi
  record_row "${E_FILENAME[$i]}" "${E_ARCH[$i]}" "${PRESENT_IDX[$i]}" "${META_IDX[$i]}" "$fetchable"
done

render_summary
