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
# Pulp Domains (e.g. "standard" vs "standard-stable"); PULP_DOMAIN covers the
# read phase below, switch_pulp_domain moves to PULP_STABLE_DOMAIN before the
# write phase.
PULP_DOMAIN="${PULP_DOMAIN:-default}"
PULP_STABLE_DOMAIN="${PULP_STABLE_DOMAIN:-default}"
# switch_pulp_domain overwrites PULP_DOMAIN itself (see api.sh) once the write
# phase starts below; content served under TESTING_BASE_PATH still lives in
# the original (testing-tier) domain, so its own copy is kept for the
# content-app fetch that happens after the switch.
TESTING_DOMAIN="$PULP_DOMAIN"

# wait for a repository-less upload task and emit the created content href,
# falling back to a stable-domain sha256 lookup (content already existing on a
# job re-run, or a task response without created_resources) -- mirrors
# deliver-rpm.sh's resolve_uploaded_content, scoped to the stable domain since
# this uploads INTO the stable domain (see the cross-domain note below).
resolve_promoted_content() {
  local task_href=$1 sha256=$2
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
  content=$(
    curl -fsSL -H "Authorization: Bearer $PULP_TOKEN" -G \
      --data-urlencode "sha256=$sha256" \
      --data-urlencode "limit=1" \
      "$PULP_URL/$PULP_STABLE_DOMAIN/api/v3/content/rpm/packages/" | jq -r '.results[0].pulp_href // empty'
  )
  if [[ -z "$content" ]]; then
    echo "::error::Cannot resolve the promoted content for task $task_href (sha256 $sha256)" >&2
    return 1
  fi
  echo "$content"
}

declare -A ARCH_CONTENT
declare -A ARCH_RESULTS
TOTAL_PACKAGES_COUNT=0

for ARCH in noarch x86_64; do
  TESTING_REPOSITORY_NAME="$TESTING_REPOSITORY_PREFIX-$ARCH"

  if ! pulp_resource_exists "repositories/rpm/rpm" "$TESTING_REPOSITORY_NAME"; then
    echo "[INFO] Testing repository $TESTING_REPOSITORY_NAME does not exist"
    continue
  fi

  VERSION_HREF=$(pulp rpm repository show --name "$TESTING_REPOSITORY_NAME" | jq -r '.latest_version_href')

  # packages of the module are identified by the label set at delivery time;
  # keep both the href list (for the content modify call) and the package
  # identity (name/version/release/arch/filename) to feed the promotion manifest
  # paginate: the testing repository keeps every delivered version, so the
  # module listing can exceed a single page
  RESULTS_FILE=$(mktemp)
  url="$PULP_URL/$PULP_DOMAIN/api/v3/content/rpm/packages/?$(
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

  # only the LATEST build of each package is promoted. Versions are compared
  # segment by segment (numeric segments as numbers): a plain string max
  # would rank "0.9" above "0.10", which bites the semver-like versions of
  # some modules. Pipeline-built versions carry no epoch or tilde.
  RESULTS=$(jq -s 'def vkey: [scan("[0-9]+|[^0-9]+") | (tonumber? // .)];
    [.[] | {pulp_href, name, version, release, arch, location_href, sha256}]
    | group_by(.name, .arch) | map(max_by([(.version | vkey), (.release | vkey)]))' "$RESULTS_FILE")
  rm -f "$RESULTS_FILE"
  CONTENT=$(echo "$RESULTS" | jq '[.[].pulp_href]')
  ARCH_PACKAGES_COUNT=$(echo "$CONTENT" | jq 'length')

  echo "[INFO] $ARCH_PACKAGES_COUNT $ARCH packages of module $MODULE_NAME found in $TESTING_REPOSITORY_NAME"
  ARCH_CONTENT[$ARCH]="$CONTENT"
  ARCH_RESULTS[$ARCH]="$RESULTS"
  TOTAL_PACKAGES_COUNT=$((TOTAL_PACKAGES_COUNT + ARCH_PACKAGES_COUNT))
done

if [[ "$TOTAL_PACKAGES_COUNT" -eq 0 ]]; then
  echo "::error::Nothing to promote, no package of module $MODULE_NAME found in $TESTING_REPOSITORY_PREFIX repositories"
  exit 1
fi

if [[ "$STABILITY" != "stable" ]]; then
  echo "[INFO] Dry run, $TOTAL_PACKAGES_COUNT packages would be promoted to $STABLE_REPOSITORY_PREFIX repositories"
  exit 0
fi

# everything from here on targets the stable-tier domain
refresh_pulp_token
switch_pulp_domain "$PULP_STABLE_DOMAIN"

for ARCH in noarch x86_64; do
  CONTENT="${ARCH_CONTENT[$ARCH]:-[]}"
  RESULTS="${ARCH_RESULTS[$ARCH]:-[]}"
  ARCH_PACKAGES_COUNT=$(echo "$CONTENT" | jq 'length')

  if [[ "$ARCH_PACKAGES_COUNT" -eq 0 ]]; then
    echo "[INFO] No $ARCH package to promote"
    continue
  fi

  STABLE_REPOSITORY_NAME="$STABLE_REPOSITORY_PREFIX-$ARCH"
  STABLE_BASE_PATH="$STABLE_BASE_PATH_PREFIX/$ARCH"
  TESTING_BASE_PATH="$TESTING_BASE_PATH_PREFIX/$ARCH"

  if ! pulp_resource_exists "repositories/rpm/rpm" "$STABLE_REPOSITORY_NAME"; then
    echo "::error::stable rpm repository $STABLE_REPOSITORY_NAME does not exist. Pulp repositories and distributions are provisioned centrally by delivery-tooling create-repos; run create-repos for this version before promoting."
    exit 1
  fi

  if ! pulp_resource_exists "distributions/rpm/rpm" "$STABLE_REPOSITORY_NAME"; then
    echo "::error::stable rpm distribution $STABLE_REPOSITORY_NAME does not exist. Pulp distributions are provisioned centrally by delivery-tooling create-repos; run create-repos for this version before promoting. Refusing to create it here to avoid an unguarded distribution."
    exit 1
  fi

  STABLE_REPOSITORY_HREF=$(pulp rpm repository show --name "$STABLE_REPOSITORY_NAME" | jq -r '.pulp_href')

  # Pulp Domains have no cross-domain content sharing at all: RepositoryVersion.
  # add_content asserts every content unit's pulp_domain matches the
  # repository's own (confirmed against the live pulpcore source), and even
  # pulp_deb's own copy_content task filters to the current domain before
  # calling add_content. Testing and stable repositories live in different
  # domains, so promoting means re-downloading each package from testing's
  # published distribution and re-uploading it fresh into the stable domain --
  # mirroring how the JFrog promote job has always worked (download from
  # testing, upload to stable: Artifactory has no content-by-reference concept
  # at all, so this was never a shortcut there in the first place).
  echo "[INFO] Re-uploading $ARCH_PACKAGES_COUNT package(s) from $TESTING_BASE_PATH into the stable domain"
  DOWNLOAD_DIR=$(mktemp -d)
  UPLOAD_DIR=$(mktemp -d)
  MAX_PARALLEL_UPLOADS=8
  mapfile -t PKG_ROWS < <(echo "$RESULTS" | jq -c '.[]')
  for i in "${!PKG_ROWS[@]}"; do
    if ((i % 40 == 0)); then
      refresh_pulp_token
    fi
    (
      refresh_pulp_token
      location_href=$(echo "${PKG_ROWS[$i]}" | jq -r '.location_href')
      dest="$DOWNLOAD_DIR/$i-$(basename "$location_href")"
      content_curl -fsSL --retry 3 --retry-delay 5 -o "$dest" "$PULP_CONTENT_URL/$TESTING_DOMAIN/$TESTING_BASE_PATH/$location_href"
      pulp_upload -F "file=@\"$dest\"" \
        "$PULP_URL/$PULP_STABLE_DOMAIN/api/v3/content/rpm/packages/" > "$UPLOAD_DIR/$i.task"
    ) &
    while (($(jobs -rp | wc -l) >= MAX_PARALLEL_UPLOADS)); do
      wait -n || true
    done
  done
  wait || true

  NEW_HREFS=()
  for i in "${!PKG_ROWS[@]}"; do
    if [[ ! -s "$UPLOAD_DIR/$i.task" ]]; then
      echo "::error::Re-upload into the stable domain failed for $(echo "${PKG_ROWS[$i]}" | jq -r '.name') ($ARCH), see the worker error above"
      exit 1
    fi
    NEW_HREFS+=("$(resolve_promoted_content "$(cat "$UPLOAD_DIR/$i.task")" "$(echo "${PKG_ROWS[$i]}" | jq -r '.sha256')")")
  done
  rm -rf "$DOWNLOAD_DIR" "$UPLOAD_DIR"
  CONTENT=$(printf '%s\n' "${NEW_HREFS[@]}" | jq -R . | jq -cs .)

  echo "[INFO] Promoting $ARCH_PACKAGES_COUNT packages to $STABLE_REPOSITORY_NAME"
  # pulp-cli repository content modify does not resolve content by pulp_href, use the api directly
  ADD_BODY_FILE=$(mktemp)
  echo "$CONTENT" | jq -c '{add_content_units: .}' > "$ADD_BODY_FILE"
  # retried like the deb path: any task can lose its worker, and concurrent
  # promotions can race on the stable repository version
  for modify_attempt in 1 2 3; do
    TASK_HREF=$(start_modify_task "$PULP_URL${STABLE_REPOSITORY_HREF}modify/" "$ADD_BODY_FILE")
    wait_task_race "$TASK_HREF" && rc=0 || rc=$?
    if [[ $rc -eq 0 ]]; then
      break
    elif [[ $rc -eq 2 && $modify_attempt -lt 3 ]]; then
      echo "[WARN] Stable repository modify was interrupted server-side, retrying"
      sleep $((modify_attempt * 15))
    else
      echo "::error::Stable repository modify failed"
      exit 1
    fi
  done

  echo "[INFO] Publishing repository $STABLE_REPOSITORY_NAME"
  create_publication rpm "$STABLE_REPOSITORY_NAME"

  # record the promoted packages (with their stable target coordinates) so the
  # verification step verifies exactly this set against the stable repo
  while read -r PKG; do
    manifest_add "$(echo "$PKG" | jq -c \
      --arg repository "$STABLE_REPOSITORY_NAME" --arg base_path "$STABLE_BASE_PATH" \
      '{filename: (.location_href | sub(".*/"; "")), name, version, release, arch, sha256, repository: $repository, base_path: $base_path}')"
  done < <(echo "${ARCH_RESULTS[$ARCH]}" | jq -c '.[]')

  echo "::notice::Packages are available at $PULP_CONTENT_URL/$PULP_STABLE_DOMAIN/$STABLE_BASE_PATH/"
done

manifest_write "$MODULE_NAME" "${DISTRIB:-}" "rpm" "$STABILITY" "promote" "$PULP_CONTENT_URL"
