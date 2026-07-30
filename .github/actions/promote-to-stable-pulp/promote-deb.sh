#!/usr/bin/env bash
set -euo pipefail

# shellcheck source=.github/scripts/pulp/manifest.sh
source "$(dirname "$0")/../../scripts/pulp/manifest.sh"
# shellcheck source=.github/scripts/pulp/api.sh
source "$(dirname "$0")/../../scripts/pulp/api.sh"

# use the org-variable values, falling back to the defaults when passed empty
# (an unset org variable is forwarded as an empty string, overriding the default)
PULP_URL="${PULP_URL:-https://pulp-api.apps.centreon.com}"
PULP_CONTENT_URL="${PULP_CONTENT_URL:-https://packages.apps.centreon.com}"
# testing (source) and stable (destination) repositories live in different
# Pulp Domains (stable is now ALWAYS a dedicated repository, for every deb
# family -- see delivery-tooling's create-deb-repos.sh); PULP_DOMAIN covers
# the read phase below, switch_pulp_domain moves to PULP_STABLE_DOMAIN before
# the write phase.
PULP_DOMAIN="${PULP_DOMAIN:-default}"
PULP_STABLE_DOMAIN="${PULP_STABLE_DOMAIN:-default}"

if ! pulp_resource_exists "repositories/deb/apt" "$REPOSITORY_NAME"; then
  echo "::error::Nothing to promote, repository $REPOSITORY_NAME does not exist"
  exit 1
fi

VERSION_HREF=$(pulp deb repository show --name "$REPOSITORY_NAME" | jq -r '.latest_version_href')

# fetch a published index into a file: 0=ok, 2=absent (404), 1=error
fetch_index() {
  local url=$1 out=$2 code
  code=$(content_curl -sSL --retry 3 --retry-delay 5 -o "$out" -w '%{http_code}' "$url") || return 1
  [[ "$code" == "200" ]] && return 0
  [[ "$code" == "404" ]] && return 2
  return 1
}

# collect the sha256 set of every package published in a suite. The served
# dists/ indexes are the source of truth for suite membership: the packages'
# relative_path cannot be trusted for that (content reused across suites
# keeps its original upload path) and the release_components api is not
# listable by the OIDC ci-user. A missing Release (404) is an empty suite;
# any other fetch failure aborts, a partial set must not drive a promotion.
suite_sha_file() {
  local base_path=$1 suite=$2 out release_file arches arch pkg_file rc
  out=$(mktemp)
  release_file=$(mktemp)
  fetch_index "$PULP_CONTENT_URL/$base_path/dists/$suite/Release" "$release_file" && rc=0 || rc=$?
  if [[ $rc -eq 2 ]]; then
    echo "$out"
    return 0
  elif [[ $rc -ne 0 ]]; then
    echo "::error::Cannot fetch the $suite Release index; refusing to promote from a partial view." >&2
    return 1
  fi
  arches=$(awk -F': ' '/^Architectures:/ {print $2}' "$release_file")
  for arch in $arches; do
    pkg_file=$(mktemp)
    if ! fetch_index "$PULP_CONTENT_URL/$base_path/dists/$suite/main/binary-$arch/Packages" "$pkg_file"; then
      echo "::error::Cannot fetch the $suite binary-$arch Packages index; refusing to promote from a partial view." >&2
      return 1
    fi
    awk -F': ' '/^SHA256:/ {print $2}' "$pkg_file" >> "$out"
    rm -f "$pkg_file"
  done
  rm -f "$release_file"
  sort -u "$out" -o "$out"
  echo "$out"
}

# download the published bytes of a testing package identified by its
# sha256/arch, resolving its served filename from the testing suite's
# checksum-indexed Packages file (the structured publication serves packages
# under the canonical debian pool layout, not their upload relative_path).
download_testing_package() {
  local sha256=$1 arch=$2 dest=$3 filename
  filename=$(
    content_curl -fsSL --retry 3 --retry-delay 5 "$PULP_CONTENT_URL/$BASE_PATH/dists/$TESTING_SUITE/main/binary-$arch/Packages" |
      awk -v sha="$sha256" 'BEGIN { RS = ""; FS = "\n" } index($0, "SHA256: " sha) { for (i = 1; i <= NF; i++) if ($i ~ /^Filename: /) { sub(/^Filename: /, "", $i); print $i } }'
  )
  if [[ -z "$filename" ]]; then
    echo "::error::Cannot locate the published file for sha256 $sha256 in $TESTING_SUITE ($arch)" >&2
    return 1
  fi
  content_curl -fsSL --retry 3 --retry-delay 5 -o "$dest" "$PULP_CONTENT_URL/$BASE_PATH/$filename"
}

TESTING_SHAS_FILE=$(suite_sha_file "$BASE_PATH" "$TESTING_SUITE")

# packages of the module are identified by the label set at delivery time.
# Paginated: the repository keeps every delivered version across all suites,
# so the module listing can exceed a single page.
RESULTS_FILE=$(mktemp)
url="$PULP_URL/$PULP_DOMAIN/api/v3/content/deb/packages/?$(
  printf 'repository_version=%s&pulp_label_select=%s&limit=1000' \
    "$(jq -rn --arg v "$VERSION_HREF" '$v | @uri')" \
    "$(jq -rn --arg v "module=$MODULE_NAME" '$v | @uri')"
)"
while [[ -n "$url" ]]; do
  refresh_pulp_token
  page=$(curl -fsSL -H "Authorization: Bearer $PULP_TOKEN" "$url")
  echo "$page" | jq -c '.results[]' >> "$RESULTS_FILE"
  url=$(echo "$page" | jq -r '.next // empty')
done

# only the LATEST version of each package is promoted: the testing suite
# accumulates every delivered build (deb has no retention mechanism).
# Versions are compared segment by segment (numeric segments as numbers): a
# plain string max would rank "0.9" above "0.10", which bites the
# semver-like versions of some modules. Pipeline-built versions carry no
# epoch or tilde.
TESTING_SET_FILE=$(mktemp)
jq -R . "$TESTING_SHAS_FILE" | jq -s 'map({key: ., value: true}) | from_entries' > "$TESTING_SET_FILE"
PACKAGES=$(
  jq -s --slurpfile testing_set "$TESTING_SET_FILE" \
    'def vkey: [scan("[0-9]+|[^0-9]+") | (tonumber? // .)];
     [.[] | select($testing_set[0][.sha256])]
     | group_by(.package, .architecture) | map(max_by(.version | vkey))' \
    "$RESULTS_FILE"
)
rm -f "$RESULTS_FILE" "$TESTING_SHAS_FILE" "$TESTING_SET_FILE"
PACKAGES_COUNT=$(echo "$PACKAGES" | jq 'length')

if [[ "$PACKAGES_COUNT" -eq 0 ]]; then
  echo "::error::Nothing to promote, no package of module $MODULE_NAME found in the $TESTING_SUITE suite of $REPOSITORY_NAME"
  exit 1
fi

echo "[INFO] $PACKAGES_COUNT packages of module $MODULE_NAME found in the $TESTING_SUITE suite of $REPOSITORY_NAME"

if [[ "$STABILITY" != "stable" ]]; then
  echo "[INFO] Dry run, $PACKAGES_COUNT packages would be promoted to $STABLE_SUITE/main"
  exit 0
fi

# everything from here on targets the stable-tier domain
refresh_pulp_token
switch_pulp_domain "$PULP_STABLE_DOMAIN"

if ! pulp_resource_exists "repositories/deb/apt" "$STABLE_REPOSITORY_NAME"; then
  echo "::error::Stable repository $STABLE_REPOSITORY_NAME does not exist. Pulp repositories are provisioned centrally by delivery-tooling create-repos; run create-repos before promoting."
  exit 1
fi
STABLE_REPOSITORY_HREF=$(pulp deb repository show --name "$STABLE_REPOSITORY_NAME" | jq -r '.pulp_href')

# "already promoted" is decided against the stable repository's OWN version:
# stable is always a dedicated repository (never a shared one), so it only
# ever receives stable content -- a promotion whose publication failed then
# reruns as a plain republish.
STABLE_SHAS_FILE=$(mktemp)
STABLE_VERSION_HREF=$(pulp deb repository show --name "$STABLE_REPOSITORY_NAME" | jq -r '.latest_version_href')
url="$PULP_URL/$PULP_DOMAIN/api/v3/content/deb/packages/?$(
  printf 'repository_version=%s&fields=sha256&limit=1000' \
    "$(jq -rn --arg v "$STABLE_VERSION_HREF" '$v | @uri')"
)"
while [[ -n "$url" ]]; do
  refresh_pulp_token
  page=$(curl -fsSL --retry 3 --retry-delay 5 -H "Authorization: Bearer $PULP_TOKEN" "$url")
  echo "$page" | jq -r '.results[].sha256' >> "$STABLE_SHAS_FILE"
  url=$(echo "$page" | jq -r '.next // empty')
done
sort -u "$STABLE_SHAS_FILE" -o "$STABLE_SHAS_FILE"

mkdir -p promoted-packages

# module label, built with jq (safe escaping) — consistent with the delivery scripts
PULP_LABELS=$(jq -cn --arg mod "$MODULE_NAME" '{"module": $mod}')

# emit the href of a stable-domain deb content unit matching the query, empty
# if absent. Every caller sits on a fallback path where the content is
# expected to exist, so an empty result is retried too: the api has been
# observed answering an empty page for content committed seconds earlier
# (stale read), and a transient error must not read as "content absent" either.
lookup_deb_content() {
  local endpoint=$1 query=$2 out attempt
  for attempt in 1 2 3 4 5; do
    out=$(curl -fsSL --retry 3 --retry-delay 5 -H "Authorization: Bearer $PULP_TOKEN" -G \
      --data-urlencode "limit=1" \
      $query \
      "$PULP_URL/$PULP_DOMAIN/api/v3/content/deb/$endpoint/" | jq -r '.results[0].pulp_href // empty') || out=""
    if [[ -n "$out" ]]; then
      echo "$out"
      return 0
    fi
    sleep $((attempt * 3))
    refresh_pulp_token
  done
}

# wait for a content-create task and emit its created content href; fall
# back to a lookup (content already existing on a job re-run)
resolve_task_content() {
  local task_href=$1 endpoint=$2 fallback_query=$3
  local body state content attempt
  for ((attempt = 0; attempt < 200; attempt++)); do
    refresh_pulp_token
    body=$(curl -fsSL -H "Authorization: Bearer $PULP_TOKEN" "$PULP_URL$task_href" 2>/dev/null) || body=""
    state=$(echo "$body" | jq -r '.state' 2>/dev/null) || state=""
    case "$state" in
      completed)
        content=$(echo "$body" | jq -r '.created_resources[0] // empty')
        if [[ -n "$content" ]]; then
          echo "$content"
          return 0
        fi
        break
        ;;
      failed | canceled)
        break
        ;;
      *)
        sleep 3
        ;;
    esac
  done
  content=$(lookup_deb_content "$endpoint" "$fallback_query")
  if [[ -z "$content" ]]; then
    echo "::error::Cannot resolve the created deb content for task $task_href" >&2
    return 1
  fi
  echo "$content"
}

# emit the release-component hrefs a package is associated with
lookup_prcs() {
  local package_href=$1
  curl -fsSL --retry 3 --retry-delay 5 -H "Authorization: Bearer $PULP_TOKEN" -G \
    --data-urlencode "package=$package_href" \
    --data-urlencode "limit=100" \
    "$PULP_URL/$PULP_DOMAIN/api/v3/content/deb/package_release_components/" \
    | jq -r '.results[].release_component'
}

# candidates already published in the stable suite need no new association;
# when EVERYTHING already reached stable (a rerun after the modify), only the
# publication may be missing: republish and stop.
UNPROMOTED_HREFS=()
while read -r PACKAGE; do
  sha=$(echo "$PACKAGE" | jq -r '.sha256')
  if ! grep -qxF "$sha" "$STABLE_SHAS_FILE"; then
    UNPROMOTED_HREFS+=("$(echo "$PACKAGE" | jq -r '.pulp_href')")
  fi
done < <(echo "$PACKAGES" | jq -c '.[]')
rm -f "$STABLE_SHAS_FILE"
if ((${#UNPROMOTED_HREFS[@]} == 0)); then
  echo "[INFO] All $PACKAGES_COUNT package(s) are already promoted to $STABLE_SUITE; republishing only"
  while read -r PACKAGE; do
    manifest_add "$(echo "$PACKAGE" | jq -c \
      --arg repository "$STABLE_REPOSITORY_NAME" --arg base_path "$STABLE_BASE_PATH" \
      --arg suite "$STABLE_SUITE" \
      '{filename: (.relative_path | sub(".*/"; "")), name: .package, version, arch: .architecture, sha256, repository: $repository, base_path: $base_path, suite: $suite, relative_path}')"
  done < <(echo "$PACKAGES" | jq -c '.[]')
  create_publication deb "$STABLE_REPOSITORY_NAME" --structured
  echo "::notice::Packages are available with: deb $PULP_CONTENT_URL/$STABLE_BASE_PATH/ $STABLE_SUITE main"
  manifest_write "$MODULE_NAME" "${DISTRIB:-}" "deb" "$STABILITY" "promote" "$PULP_CONTENT_URL"
  exit 0
fi

# batched promote, mirroring the batched delivery. Pulp Domains have no
# cross-domain content sharing at all: RepositoryVersion.add_content asserts
# every content unit's pulp_domain matches the repository's own (confirmed
# against the live pulpcore source), and even pulp_deb's own copy_content task
# filters to the current domain before calling add_content. Testing and
# stable repositories live in different domains, so promoting means
# re-downloading each package from testing's published distribution and
# re-uploading it fresh into the stable domain -- mirroring how the JFrog
# promote job has always worked (download from testing, upload to stable).
#
# The FIRST package of each architecture is re-uploaded through the legacy
# path - it get_or_creates the stable ReleaseComponent and ReleaseArchitecture,
# which the ci user cannot create nor list directly - then the stable
# release-component href is deduced from its associations, every other
# package is re-uploaded unassociated and associated explicitly, and one
# modify adds the whole batch to the stable repository.
declare -A ARCH_SEEN=()
LEGACY_PACKAGES=()
BATCH_PACKAGES=()
while read -r PACKAGE; do
  ARCH=$(echo "$PACKAGE" | jq -r '.architecture')
  if [[ -z "${ARCH_SEEN[$ARCH]+set}" ]]; then
    ARCH_SEEN[$ARCH]=1
    LEGACY_PACKAGES+=("$PACKAGE")
  else
    BATCH_PACKAGES+=("$PACKAGE")
  fi
done < <(echo "$PACKAGES" | jq -c '.[]')

# the stable release component is deduced from the association the legacy
# re-upload of the FIRST reference creates: capture its stable-domain
# association set (if the sha already exists there, e.g. a rerun) before the
# upload so the new one stands out as the difference.
refresh_pulp_token
LEGACY_REF_SHA256=$(echo "${LEGACY_PACKAGES[0]}" | jq -r '.sha256')
LEGACY_REF_BEFORE_HREF=$(lookup_deb_content "packages" "--data-urlencode sha256=$LEGACY_REF_SHA256")
LEGACY_REF_BEFORE=""
[[ -n "$LEGACY_REF_BEFORE_HREF" ]] && LEGACY_REF_BEFORE=$(lookup_prcs "$LEGACY_REF_BEFORE_HREF" | sort)

for PACKAGE in "${LEGACY_PACKAGES[@]}"; do
  RELATIVE_PATH=$(echo "$PACKAGE" | jq -r '.relative_path')
  SHA256=$(echo "$PACKAGE" | jq -r '.sha256')
  ARCH=$(echo "$PACKAGE" | jq -r '.architecture')
  FILE_NAME=$(basename "$RELATIVE_PATH")
  FILE="promoted-packages/$FILE_NAME"

  echo "[INFO] Downloading $FILE_NAME from $TESTING_SUITE"
  download_testing_package "$SHA256" "$ARCH" "$FILE"

  # re-upload the downloaded bytes with the SAME relative_path into the
  # stable domain: pulp reuses the content if it already exists there and
  # only adds the stable suite association, creating the stable
  # ReleaseComponent/ReleaseArchitecture on the way.
  # retried: the legacy path creates a repository version and can lose the
  # version race against a concurrent promotion.
  for legacy_attempt in 1 2 3; do
    echo "[INFO] Promoting $FILE_NAME to $STABLE_SUITE/main [legacy path, attempt $legacy_attempt]"
    TASK_HREF=$(
      pulp_upload \
        -F "file=@\"$FILE\"" \
        -F "relative_path=$RELATIVE_PATH" \
        -F "distribution=$STABLE_SUITE" \
        -F "component=main" \
        -F "repository=$STABLE_REPOSITORY_HREF" \
        -F "pulp_labels=$PULP_LABELS" \
        "$PULP_URL/$PULP_DOMAIN/api/v3/content/deb/packages/"
    )
    wait_task_race "$TASK_HREF" && rc=0 || rc=$?
    if [[ $rc -eq 0 ]]; then
      break
    elif [[ $rc -eq 2 && $legacy_attempt -lt 3 ]]; then
      echo "[WARN] Legacy promotion of $FILE_NAME lost the repository-version race, retrying"
      sleep $((legacy_attempt * 15))
    else
      echo "::error::Legacy promotion of $FILE_NAME failed"
      exit 1
    fi
  done
done

if ((${#BATCH_PACKAGES[@]} > 0)); then
  # the stable release component is the association the legacy re-upload just
  # added to the reference package. On a rerun the association pre-exists and
  # the difference is empty: the reference then carries every suite
  # association it had before (stable, possibly others for reused content).
  refresh_pulp_token
  LEGACY_REF_STABLE_HREF=$(lookup_deb_content "packages" "--data-urlencode sha256=$LEGACY_REF_SHA256")
  if [[ -z "$LEGACY_REF_STABLE_HREF" ]]; then
    echo "::error::Cannot resolve the promoted reference package (sha256 $LEGACY_REF_SHA256) in the stable domain"
    exit 1
  fi
  LEGACY_REF_AFTER=$(lookup_prcs "$LEGACY_REF_STABLE_HREF" | sort)
  STABLE_RC_SET=$(comm -13 <(echo "$LEGACY_REF_BEFORE") <(echo "$LEGACY_REF_AFTER") | grep . || true)
  if [[ $(echo "$STABLE_RC_SET" | grep -c .) -ne 1 ]]; then
    # dedicated stable repository: it only ever receives stable suite
    # associations, so any association on its latest version points to the
    # stable release component. Covers a rerun where the diff above is empty.
    refresh_pulp_token
    STABLE_LATEST=$(pulp deb repository show --name "$STABLE_REPOSITORY_NAME" | jq -r '.latest_version_href')
    STABLE_RC_SET=$(
      curl -fsSL --retry 3 --retry-delay 5 -H "Authorization: Bearer $PULP_TOKEN" \
        "$PULP_URL/$PULP_DOMAIN/api/v3/content/deb/package_release_components/?$(
          printf 'repository_version=%s&limit=1' "$(jq -rn --arg v "$STABLE_LATEST" '$v | @uri')"
        )" | jq -r '.results[0].release_component // empty'
    )
  fi
  STABLE_RC=$(echo "$STABLE_RC_SET" | grep . | head -1)
  if [[ -z "$STABLE_RC" ]]; then
    # a promotion interrupted after its repository modify only misses the
    # publication: republish and re-check every candidate before giving up
    echo "[WARN] Cannot deduce the $STABLE_SUITE/main release component (got: ${STABLE_RC_SET:-none}); republishing to check for an interrupted promotion"
    create_publication deb "$STABLE_REPOSITORY_NAME" --structured
    RECHECK_SHAS_FILE=$(suite_sha_file "$STABLE_BASE_PATH" "$STABLE_SUITE")
    MISSING_COUNT=0
    while read -r sha; do
      grep -qxF "$sha" "$RECHECK_SHAS_FILE" || MISSING_COUNT=$((MISSING_COUNT + 1))
    done < <(echo "$PACKAGES" | jq -r '.[].sha256')
    rm -f "$RECHECK_SHAS_FILE"
    if ((MISSING_COUNT == 0)); then
      echo "[INFO] Every candidate package is published in $STABLE_SUITE; the interrupted promotion only missed the publication"
      while read -r PACKAGE; do
        manifest_add "$(echo "$PACKAGE" | jq -c \
          --arg repository "$STABLE_REPOSITORY_NAME" --arg base_path "$STABLE_BASE_PATH" \
          --arg suite "$STABLE_SUITE" \
          '{filename: (.relative_path | sub(".*/"; "")), name: .package, version, arch: .architecture, sha256, repository: $repository, base_path: $base_path, suite: $suite, relative_path}')"
      done < <(echo "$PACKAGES" | jq -c '.[]')
      echo "::notice::Packages are available with: deb $PULP_CONTENT_URL/$STABLE_BASE_PATH/ $STABLE_SUITE main"
      manifest_write "$MODULE_NAME" "${DISTRIB:-}" "deb" "$STABILITY" "promote" "$PULP_CONTENT_URL"
      exit 0
    fi
    echo "::error::Cannot deduce the $STABLE_SUITE/main release component (got: ${STABLE_RC_SET:-none}) and $MISSING_COUNT candidate package(s) are missing from the published $STABLE_SUITE suite. A previous promotion likely left the suite associations without the repository content; deliver a rebuilt (fresh) build or add the packages to the stable repository manually."
    exit 1
  fi
  echo "[INFO] Release component of $STABLE_SUITE/main: $STABLE_RC"

  # re-download each remaining package from testing and re-upload it
  # unassociated into the stable domain (parallel pool of 8, marker-file
  # based like rpm/deliver-deb), then associate it with the stable release
  # component.
  echo "[INFO] Re-uploading ${#BATCH_PACKAGES[@]} package(s) from $BASE_PATH into the stable domain"
  DOWNLOAD_DIR=$(mktemp -d)
  MAX_PARALLEL=8
  for i in "${!BATCH_PACKAGES[@]}"; do
    if ((i % 40 == 0)); then
      refresh_pulp_token
    fi
    (
      refresh_pulp_token
      PACKAGE="${BATCH_PACKAGES[$i]}"
      RELATIVE_PATH=$(echo "$PACKAGE" | jq -r '.relative_path')
      SHA256=$(echo "$PACKAGE" | jq -r '.sha256')
      ARCH=$(echo "$PACKAGE" | jq -r '.architecture')
      FILE_NAME=$(basename "$RELATIVE_PATH")
      FILE="$DOWNLOAD_DIR/$i-$FILE_NAME"
      download_testing_package "$SHA256" "$ARCH" "$FILE"
      pulp_upload \
        -F "file=@\"$FILE\"" \
        -F "relative_path=$RELATIVE_PATH" \
        -F "pulp_labels=$PULP_LABELS" \
        "$PULP_URL/$PULP_DOMAIN/api/v3/content/deb/packages/" > "$DOWNLOAD_DIR/$i.task"
    ) &
    while (($(jobs -rp | wc -l) >= MAX_PARALLEL)); do
      wait -n || true
    done
  done
  wait || true

  for i in "${!BATCH_PACKAGES[@]}"; do
    if [[ ! -s "$DOWNLOAD_DIR/$i.task" ]]; then
      echo "::error::Re-upload into the stable domain failed for $(echo "${BATCH_PACKAGES[$i]}" | jq -r '.package') ($(echo "${BATCH_PACKAGES[$i]}" | jq -r '.architecture')), see the worker error above"
      exit 1
    fi
  done

  echo "[INFO] Resolving ${#BATCH_PACKAGES[@]} uploaded package(s)"
  for i in "${!BATCH_PACKAGES[@]}"; do
    if ((i % 40 == 0)); then
      refresh_pulp_token
    fi
    (
      resolve_task_content "$(cat "$DOWNLOAD_DIR/$i.task")" "packages" \
        "--data-urlencode sha256=$(echo "${BATCH_PACKAGES[$i]}" | jq -r '.sha256')" > "$DOWNLOAD_DIR/$i.content"
    ) &
    while (($(jobs -rp | wc -l) >= MAX_PARALLEL)); do
      wait -n || true
    done
  done
  wait || true
  PACKAGE_HREFS=()
  for i in "${!BATCH_PACKAGES[@]}"; do
    if [[ ! -s "$DOWNLOAD_DIR/$i.content" ]]; then
      echo "::error::Cannot resolve the promoted content for $(echo "${BATCH_PACKAGES[$i]}" | jq -r '.package') (see the worker error above)"
      exit 1
    fi
    PACKAGE_HREFS+=("$(cat "$DOWNLOAD_DIR/$i.content")")
  done
  rm -rf "$DOWNLOAD_DIR"

  # associate every re-uploaded package with the stable release component.
  # The package_release_components create is a plain synchronous DRF create
  # (201 with the created unit, no task), so the href comes straight out of
  # the response; a failed create (association already existing on a job
  # re-run) falls back to a lookup.
  echo "[INFO] Associating ${#PACKAGE_HREFS[@]} package(s) with $STABLE_SUITE/main"
  PRC_DIR=$(mktemp -d)
  for i in "${!PACKAGE_HREFS[@]}"; do
    if ((i % 40 == 0)); then
      refresh_pulp_token
    fi
    (
      refresh_pulp_token
      package_href="${PACKAGE_HREFS[$i]}"
      out=$(post_json "$PULP_URL/$PULP_DOMAIN/api/v3/content/deb/package_release_components/" \
        "{\"package\": \"$package_href\", \"release_component\": \"$STABLE_RC\"}") || out=$'\n000'
      code="${out##*$'\n'}"
      body="${out%$'\n'*}"
      href=""
      if [[ "$code" == 2* ]]; then
        # tolerate a non-json body (gateway error page behind a 2xx): a jq
        # failure inside the substitution would silently kill this subshell
        href=$(echo "$body" | jq -r '.pulp_href // empty' 2>/dev/null) || href=""
      fi
      if [[ -z "$href" ]]; then
        # the api answers 500 on a duplicate synchronous content create; on a
        # rerun the stable association simply pre-exists (see the lookup below)
        echo "[INFO] $(echo "${BATCH_PACKAGES[$i]}" | jq -r '.package'): stable association create answered HTTP $code, falling back to the lookup (expected on a rerun)"
        href=$(lookup_deb_content "package_release_components" \
          "--data-urlencode package=$package_href --data-urlencode release_component=$STABLE_RC")
      fi
      printf '%s\n%s' "$package_href" "$href" > "$PRC_DIR/$i.pair"
    ) &
    while (($(jobs -rp | wc -l) >= MAX_PARALLEL)); do
      wait -n || true
    done
  done
  wait || true

  ADD_UNITS_FILE=$(mktemp)
  for i in "${!BATCH_PACKAGES[@]}"; do
    if [[ ! -s "$PRC_DIR/$i.pair" ]] || [[ -z "$(sed -n '2p' "$PRC_DIR/$i.pair")" ]]; then
      echo "::error::Stable suite association failed for package $(echo "${BATCH_PACKAGES[$i]}" | jq -r '.package') (see the worker error above)"
      exit 1
    fi
    cat "$PRC_DIR/$i.pair" >> "$ADD_UNITS_FILE"
    echo >> "$ADD_UNITS_FILE"
  done
  rm -rf "$PRC_DIR"

  echo "[INFO] Adding ${#BATCH_PACKAGES[@]} package(s) and their suite associations to $STABLE_REPOSITORY_NAME in a single task"
  refresh_pulp_token
  # body through a file: thousands of hrefs exceed the argv limit
  ADD_BODY_FILE=$(mktemp)
  jq -R . "$ADD_UNITS_FILE" | jq -cs '{add_content_units: [.[] | select(. != "")]}' > "$ADD_BODY_FILE"
  rm -f "$ADD_UNITS_FILE"
  # retried like the legacy uploads: the modify also creates a repository
  # version and can lose the same race
  for modify_attempt in 1 2 3; do
    MODIFY_TASK=$(start_modify_task "$PULP_URL${STABLE_REPOSITORY_HREF}modify/" "$ADD_BODY_FILE")
    wait_task_race "$MODIFY_TASK" && rc=0 || rc=$?
    if [[ $rc -eq 0 ]]; then
      break
    elif [[ $rc -eq 2 && $modify_attempt -lt 3 ]]; then
      echo "[WARN] Stable repository modify lost the repository-version race, retrying"
      sleep $((modify_attempt * 15))
    else
      echo "::error::Stable repository modify failed"
      exit 1
    fi
  done
fi

# record the promoted packages (with their stable suite coordinates) so the
# verification step verifies exactly this set against the stable suite
while read -r PACKAGE; do
  manifest_add "$(echo "$PACKAGE" | jq -c \
    --arg repository "$STABLE_REPOSITORY_NAME" --arg base_path "$STABLE_BASE_PATH" \
    --arg suite "$STABLE_SUITE" \
    '{filename: (.relative_path | sub(".*/"; "")), name: .package, version, arch: .architecture, sha256, repository: $repository, base_path: $base_path, suite: $suite, relative_path}')"
done < <(echo "$PACKAGES" | jq -c '.[]')

echo "[INFO] Publishing repository $STABLE_REPOSITORY_NAME"
create_publication deb "$STABLE_REPOSITORY_NAME" --structured

echo "::notice::Packages are available with: deb $PULP_CONTENT_URL/$STABLE_BASE_PATH/ $STABLE_SUITE main"

manifest_write "$MODULE_NAME" "${DISTRIB:-}" "deb" "$STABILITY" "promote" "$PULP_CONTENT_URL"
