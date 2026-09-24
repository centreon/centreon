#!/usr/bin/env bash
set -euo pipefail
shopt -s nullglob

# shellcheck source=.github/scripts/pulp/manifest.sh
source "$(dirname "$0")/../../scripts/pulp/manifest.sh"
# shellcheck source=.github/scripts/pulp/api.sh
source "$(dirname "$0")/../../scripts/pulp/api.sh"

# an unset org variable is forwarded as an empty string, overriding the default
PULP_URL="${PULP_URL:-https://pulp-api.int.centreon.com}"
PULP_CONTENT_URL="${PULP_CONTENT_URL:-https://packages.int.centreon.com}"
PULP_DOMAIN="${PULP_DOMAIN:-default}"
# read through the public content-app, no auth/grant needed (see api.sh's content_curl)
PULP_STABLE_DOMAIN="${PULP_STABLE_DOMAIN:-default}"

STABLE_ROOT="${LEGACY_STABLE_BASE_PATH:-$PULP_STABLE_DOMAIN/${STABLE_BASE_PATH:-$BASE_PATH}}"
# the dists listing is only served on the domain path, not through the legacy front
STABLE_REPOSITORY_ROOT="$PULP_STABLE_DOMAIN/${STABLE_BASE_PATH:-$BASE_PATH}"

# print a stable repository file (empty output = 404, i.e. not published).
# Fails closed (non-zero) on anything but a clean 404.
fetch_stable_file() {
  local url=$1 out_file http_code
  out_file=$(mktemp)
  http_code=$(content_curl -sSL --retry 3 --retry-delay 5 -o "$out_file" -w '%{http_code}' "$url" 2>/dev/null || echo 000)
  case "$http_code" in
    404) rm -f "$out_file"; return 0 ;;
    200) cat "$out_file"; rm -f "$out_file" ;;
    *)
      rm -f "$out_file"
      echo "::error::Cannot read $url (HTTP $http_code) to guard the stable packages; refusing to deliver. Retry once the content endpoint is reachable." >&2
      return 1
      ;;
  esac
}

# print one "suite package version architecture sha256 filename" line per
# package the stable repository publishes. The publication lays every suite out
# in one flat pool (pool/main/<prefix>/<name>/<file>), so a build published in
# another version's stable suite shares the delivered file's pool path, and the
# legacy front resolves pool paths stable first.
load_stable_packages() {
  local listing listed suites suite root release arches a packages
  listing=$(fetch_stable_file "$PULP_CONTENT_URL/$STABLE_REPOSITORY_ROOT/dists/") || return 1
  listed=$(grep -oE 'href="(\./)?[^"/?]+/"' <<< "$listing" | sed -E 's|href="(\./)?([^"/?]+)/"|\2|' | grep -vx '\.\.' || true)
  # a listing without any link isn't the expected directory index: guarding
  # the target suite only would let a shared-pool collision through
  if [[ -n "$listing" ]] && ! grep -q 'href="' <<< "$listing"; then
    echo "::error::Cannot parse the stable suites from $STABLE_REPOSITORY_ROOT/dists/; refusing to deliver." >&2
    return 1
  fi
  suites=$(printf '%s\n' "$STABLE_SUITE" "$listed" | awk 'NF && !seen[$0]++')
  for suite in $suites; do
    root=$STABLE_REPOSITORY_ROOT
    [[ "$suite" == "$STABLE_SUITE" ]] && root=$STABLE_ROOT
    release=$(fetch_stable_file "$PULP_CONTENT_URL/$root/dists/$suite/Release") || return 1
    # no Release yet (fresh suite) -> nothing published
    [[ -z "$release" ]] && continue
    # "Architecture: all" packages are listed in every binary-<arch>/Packages
    # index the suite advertises; there is no binary-all index (404, skipped)
    arches=$(awk -F': ' '/^Architectures:/ {print $2}' <<< "$release")
    for a in $arches; do
      packages=$(fetch_stable_file "$PULP_CONTENT_URL/$root/dists/$suite/main/binary-$a/Packages") || return 1
      [[ -z "$packages" ]] && continue
      awk -v s="$suite" '
        BEGIN { RS = ""; FS = "\n" }
        {
          n = v = ar = h = f = ""
          for (i = 1; i <= NF; i++) {
            if ($i ~ /^Package: /) n = substr($i, 10)
            else if ($i ~ /^Version: /) v = substr($i, 10)
            else if ($i ~ /^Architecture: /) ar = substr($i, 15)
            else if ($i ~ /^SHA256: /) h = substr($i, 9)
            else if ($i ~ /^Filename: /) f = substr($i, 11)
          }
          if (n != "") print s, n, v, ar, h, f
        }
      ' <<< "$packages"
    done
  done
}

# the stable repository holds a single build per (package, version, arch) for
# every suite: when another version's stable suite already publishes this
# package version, deliver that exact build instead of the local one, so the
# testing index matches the served file and the promotion can associate it
# with this version's stable suite too
adopt_stable_build() {
  local file=$1 name version arch sha256 entry other_suite stable_sha256 stable_filename tmp_file
  name=$(dpkg-deb -f "$file" Package)
  version=$(dpkg-deb -f "$file" Version)
  arch=$(dpkg-deb -f "$file" Architecture)
  sha256=$(sha256sum "$file" | cut -d' ' -f1)
  # already in this version's stable suite: assert_not_in_stable refuses it
  if awk -v s="$STABLE_SUITE" -v n="$name" -v v="$version" -v a="$arch" \
       '$1 == s && $2 == n && $3 == v && $4 == a { found = 1 } END { exit found ? 0 : 1 }' "$STABLE_PACKAGES"; then
    return 0
  fi
  entry=$(awk -v s="$STABLE_SUITE" -v n="$name" -v v="$version" -v a="$arch" -v h="$sha256" \
    '$1 != s && $2 == n && $3 == v && $4 == a && $5 != h { print $1, $5, $6; exit }' "$STABLE_PACKAGES")
  [[ -z "$entry" ]] && return 0
  read -r other_suite stable_sha256 stable_filename <<< "$entry"

  tmp_file=$(mktemp)
  if ! content_curl -fsSL --retry 3 --retry-delay 5 -o "$tmp_file" "$PULP_CONTENT_URL/$STABLE_REPOSITORY_ROOT/$stable_filename"; then
    rm -f "$tmp_file"
    echo "::error::Cannot download the $other_suite build of $name $version ($arch) to deliver it instead of the local one; refusing to deliver."
    return 1
  fi
  if [[ "$(sha256sum "$tmp_file" | cut -d' ' -f1)" != "$stable_sha256" \
     || "$(dpkg-deb -f "$tmp_file" Package)" != "$name" \
     || "$(dpkg-deb -f "$tmp_file" Version)" != "$version" \
     || "$(dpkg-deb -f "$tmp_file" Architecture)" != "$arch" ]]; then
    rm -f "$tmp_file"
    echo "::error::The downloaded $other_suite build of $name $version ($arch) doesn't match its index entry; refusing to deliver."
    return 1
  fi
  mv -f "$tmp_file" "$file"
  chmod 644 "$file"
  ADOPTED[$file]=1
  echo "::warning::$name $version ($arch) is already published in the stable suite $other_suite (sha256 $stable_sha256): delivering that build instead of the local one (sha256 $sha256). Bump the package version to ship a distinct build."
}

# refuse delivering a package version already published in the stable suite
# (pulp dedupes by (package, version, arch) repository-wide, so re-delivering
# would silently evict the stable one), or another build of a version published
# in any other stable suite (same pool path, the stable file would be served;
# adopt_stable_build normally replaced it beforehand). The release_components
# api isn't listable by the OIDC ci-user (403), so this reads the served
# Packages indexes loaded into STABLE_PACKAGES.
assert_not_in_stable() {
  local file=$1 name version arch sha256 conflict
  name=$(dpkg-deb -f "$file" Package)
  version=$(dpkg-deb -f "$file" Version)
  arch=$(dpkg-deb -f "$file" Architecture)
  sha256=$(sha256sum "$file" | cut -d' ' -f1)

  if awk -v s="$STABLE_SUITE" -v n="$name" -v v="$version" -v a="$arch" \
       '$1 == s && $2 == n && $3 == v && $4 == a { found = 1 } END { exit found ? 0 : 1 }' "$STABLE_PACKAGES"; then
    echo "::error::$name $version ($arch) is already published in the stable suite $STABLE_SUITE; refusing to deliver it to $SUITE. Bump the package version for a new build."
    return 1
  fi

  conflict=$(awk -v n="$name" -v v="$version" -v a="$arch" -v h="$sha256" \
    '$2 == n && $3 == v && $4 == a && $5 != h { print $1; exit }' "$STABLE_PACKAGES")
  if [[ -n "$conflict" ]]; then
    echo "::error::Another build of $name $version ($arch) is published in the stable suite $conflict and shares its pool path; refusing to deliver it to $SUITE, the stable file would be served instead. Bump the package version."
    return 1
  fi
}

FILES=(*.deb)
if [[ ${#FILES[@]} -eq 0 ]]; then
  echo "::error::No deb package found to deliver"
  exit 1
fi

if ! pulp_resource_exists "repositories/deb/apt" "$REPOSITORY_NAME"; then
  echo "::error::deb repository $REPOSITORY_NAME does not exist. Pulp repositories and distributions are provisioned centrally by delivery-tooling create-repos; run create-repos for this version before delivering."
  exit 1
fi

if ! pulp_resource_exists "distributions/deb/apt" "$REPOSITORY_NAME"; then
  echo "::error::deb distribution $REPOSITORY_NAME does not exist. Pulp distributions are provisioned centrally by delivery-tooling create-repos; run create-repos for this version before delivering. Refusing to create it here to avoid an unguarded distribution."
  exit 1
fi

REPOSITORY_HREF=$(pulp deb repository show --name "$REPOSITORY_NAME" | jq -r '.pulp_href')

# loaded once, read by every assert_not_in_stable (parallel uploads included);
# adoptions happen before anything reads the local files' checksums
STABLE_PACKAGES=$(mktemp)
declare -A ADOPTED=()
if [[ "${STABILITY:-}" != "unstable" ]]; then
  refresh_pulp_token
  load_stable_packages > "$STABLE_PACKAGES"
  for FILE in "${FILES[@]}"; do
    adopt_stable_build "$FILE"
  done
fi

# record every delivered package in the manifest for the verification step,
# publish, and write the manifest
record_and_publish() {
  local file name version arch sha256
  for file in "${FILES[@]}"; do
    name=$(dpkg-deb -f "$file" Package)
    version=$(dpkg-deb -f "$file" Version)
    arch=$(dpkg-deb -f "$file" Architecture)
    sha256=$(sha256sum "$file" | cut -d' ' -f1)
    manifest_add "$(jq -cn \
      --arg filename "$file" --arg name "$name" --arg version "$version" \
      --arg arch "$arch" --arg sha256 "$sha256" --arg repository "$REPOSITORY_NAME" \
      --arg base_path "${LEGACY_BASE_PATH:-$PULP_DOMAIN/$BASE_PATH}" --arg suite "$SUITE" --arg relative_path "$POOL_PATH/$file" \
      '{filename:$filename,name:$name,version:$version,arch:$arch,sha256:$sha256,repository:$repository,base_path:$base_path,suite:$suite,relative_path:$relative_path}')"
  done

  echo "[INFO] Publishing repository $REPOSITORY_NAME"
  create_publication deb "$REPOSITORY_NAME" --structured

  echo "::notice::Packages are available with: deb $PULP_CONTENT_URL/${LEGACY_BASE_PATH:-$PULP_DOMAIN/$BASE_PATH}/ $SUITE main"

  manifest_write "$MODULE_NAME" "${DISTRIB:-}" "deb" "${STABILITY:-}" "delivery" "$PULP_CONTENT_URL"
}

# an adopted build already published in the target suite (a previous delivery
# of the same version) needs no upload: it is reused content whose suite
# association pre-exists, from which no release component can be deduced
DELIVER_FILES=("${FILES[@]}")
if ((${#ADOPTED[@]} > 0)); then
  TARGET_ROOT="${LEGACY_BASE_PATH:-$PULP_DOMAIN/$BASE_PATH}"
  TARGET_SHA256S=""
  TARGET_RELEASE=$(fetch_stable_file "$PULP_CONTENT_URL/$TARGET_ROOT/dists/$SUITE/Release")
  read -ra TARGET_ARCHES <<< "$(awk -F': ' '/^Architectures:/ {print $2}' <<< "$TARGET_RELEASE")"
  for a in "${TARGET_ARCHES[@]}"; do
    TARGET_SHA256S+=$(fetch_stable_file "$PULP_CONTENT_URL/$TARGET_ROOT/dists/$SUITE/main/binary-$a/Packages" | awk -F': ' '/^SHA256:/ {print $2}')
    TARGET_SHA256S+=$'\n'
  done
  DELIVER_FILES=()
  for FILE in "${FILES[@]}"; do
    if [[ -n "${ADOPTED[$FILE]+set}" ]] && grep -qxF "$(sha256sum "$FILE" | cut -d' ' -f1)" <<< "$TARGET_SHA256S"; then
      echo "[INFO] $FILE: the adopted build is already published in $SUITE, nothing to upload"
    else
      DELIVER_FILES+=("$FILE")
    fi
  done
  if ((${#DELIVER_FILES[@]} == 0)); then
    record_and_publish
    exit 0
  fi
fi

PULP_LABELS=$(jq -cn \
  --arg mod        "$MODULE_NAME" \
  --arg git_commit "${GITHUB_SHA:-}" \
  --arg git_ref    "${GITHUB_REF:-}" \
  --arg run_id     "${GITHUB_RUN_ID:-}" \
  --arg actor      "${GITHUB_ACTOR:-}" \
  --arg workflow   "${GITHUB_WORKFLOW:-}" \
  '{"module": $mod, "git_commit": $git_commit, "git_ref": $git_ref, "github_run_id": $run_id, "github_actor": $actor, "github_workflow": $workflow}')

# a repository-less deb upload ignores distribution/component, so the FIRST
# package of each arch goes through the legacy repository path instead to
# establish the suite's release component; the rest upload unassociated and
# get associated explicitly, then all of it is added in a single modify.
lookup_deb_content() {
  # emit the href of a matching deb content unit, empty if absent. Retried:
  # the api has been observed answering an empty page for content committed
  # seconds earlier (stale read)
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

resolve_task_content() {
  # wait for a content-create task and emit its created content href; fall
  # back to a lookup (content already existing on a job re-run)
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

# pick one file per arch for the legacy path. Prefer content that doesn't
# exist in pulp yet: a fresh upload carries exactly one suite association
# afterwards, identifying the release component unambiguously. For a reused
# representative (identical bytes delivered before), capture its pre-upload
# associations to diff against later.
declare -A LEGACY_FOR_ARCH=()
declare -A LEGACY_FRESH=()
declare -A LEGACY_BEFORE=()
refresh_pulp_token
for i in "${!DELIVER_FILES[@]}"; do
  FILE=${DELIVER_FILES[$i]}
  arch=$(dpkg-deb -f "$FILE" Architecture)
  if [[ -n "${LEGACY_FRESH[$arch]+set}" ]]; then
    continue
  fi
  if ((i % 40 == 0)); then
    refresh_pulp_token
  fi
  sha=$(sha256sum "$FILE" | cut -d' ' -f1)
  existing=$(lookup_deb_content "packages" "--data-urlencode sha256=$sha")
  if [[ -z "$existing" ]]; then
    LEGACY_FOR_ARCH[$arch]="$FILE"
    LEGACY_FRESH[$arch]=1
  elif [[ -z "${LEGACY_FOR_ARCH[$arch]+set}" ]]; then
    LEGACY_FOR_ARCH[$arch]="$FILE"
    LEGACY_BEFORE[$arch]=$(lookup_prcs "$existing" | sort)
  fi
done

declare -A IS_LEGACY=()
LEGACY_FILES=()
LEGACY_ARCHS=()
for arch in "${!LEGACY_FOR_ARCH[@]}"; do
  LEGACY_FILES+=("${LEGACY_FOR_ARCH[$arch]}")
  LEGACY_ARCHS+=("$arch")
  IS_LEGACY["${LEGACY_FOR_ARCH[$arch]}"]=1
done
ORPHAN_FILES=()
for FILE in "${DELIVER_FILES[@]}"; do
  [[ -n "${IS_LEGACY[$FILE]+set}" ]] || ORPHAN_FILES+=("$FILE")
done

refresh_pulp_token
for FILE in "${LEGACY_FILES[@]}"; do
  assert_not_in_stable "$FILE"
done
# sequential with retry: the legacy path creates a repository version, and
# concurrent legs of the shared repository can lose the version race (rc 2)
for FILE in "${LEGACY_FILES[@]}"; do
  for legacy_attempt in 1 2 3; do
    echo "[INFO] Uploading $FILE to $POOL_PATH/ ($SUITE/main, module $MODULE_NAME) [legacy path, attempt $legacy_attempt]"
    LEGACY_TASK=$(
      pulp_upload \
        -F "file=@\"$FILE\"" \
        -F "relative_path=$POOL_PATH/$FILE" \
        -F "distribution=$SUITE" \
        -F "component=main" \
        -F "repository=$REPOSITORY_HREF" \
        -F "pulp_labels=$PULP_LABELS" \
        "$PULP_URL/$PULP_DOMAIN/api/v3/content/deb/packages/"
    )
    wait_task_race "$LEGACY_TASK" && rc=0 || rc=$?
    if [[ $rc -eq 0 ]]; then
      break
    elif [[ $rc -eq 2 && $legacy_attempt -lt 3 ]]; then
      echo "[WARN] Legacy upload of $FILE lost the repository-version race against a concurrent delivery, retrying"
      sleep $((legacy_attempt * 15))
    else
      echo "::error::Legacy upload of $FILE failed"
      exit 1
    fi
  done
done

# deduce the release component href from the association the legacy uploads
# just created: a fresh representative now carries exactly one association; a
# reused one carries its prior ones too, so diff post- against pre-upload
refresh_pulp_token
RELEASE_COMPONENT_HREF=""
for i in "${!LEGACY_FILES[@]}"; do
  FILE=${LEGACY_FILES[$i]}
  arch=${LEGACY_ARCHS[$i]}
  sha=$(sha256sum "$FILE" | cut -d' ' -f1)
  href=$(lookup_deb_content "packages" "--data-urlencode sha256=$sha")
  if [[ -z "$href" ]]; then
    echo "::error::Cannot find the legacy-delivered package $FILE (sha256 $sha)"
    exit 1
  fi
  after=$(lookup_prcs "$href" | sort)
  if [[ -n "${LEGACY_FRESH[$arch]+set}" ]]; then
    if [[ $(echo "$after" | grep -c .) -eq 1 ]]; then
      RELEASE_COMPONENT_HREF="$after"
      break
    fi
  else
    new_rcs=$(comm -13 <(echo "${LEGACY_BEFORE[$arch]}") <(echo "$after") | grep . || true)
    if [[ $(echo "$new_rcs" | grep -c .) -eq 1 ]]; then
      RELEASE_COMPONENT_HREF="$new_rcs"
      break
    elif [[ $(echo "$after" | grep -c .) -eq 1 ]]; then
      # rerun of a partially delivered leg: empty diff, but it's the only association
      RELEASE_COMPONENT_HREF=$(echo "$after" | grep .)
      break
    fi
  fi
done
if [[ -z "$RELEASE_COMPONENT_HREF" ]]; then
  echo "::error::Cannot deduce the $SUITE/main release component: every legacy representative is reused content whose suite association pre-exists. Deliver a rebuilt (fresh) package or clean up the previous partial delivery."
  exit 1
fi
echo "[INFO] Release component of $SUITE/main: $RELEASE_COMPONENT_HREF"

# parallel repository-less uploads (pool of 8), marker-file based like rpm
UPLOAD_DIR=$(mktemp -d)
MAX_PARALLEL_UPLOADS=8
for i in "${!ORPHAN_FILES[@]}"; do
  FILE=${ORPHAN_FILES[$i]}
  if ((i % 40 == 0)); then
    refresh_pulp_token
  fi
  (
    # subshell-local: the inherited token can go stale between parent refreshes
    refresh_pulp_token
    assert_not_in_stable "$FILE"
    echo "[INFO] Uploading $FILE to $POOL_PATH/ ($SUITE/main, module $MODULE_NAME)"
    pulp_upload \
      -F "file=@\"$FILE\"" \
      -F "relative_path=$POOL_PATH/$FILE" \
      -F "pulp_labels=$PULP_LABELS" \
      "$PULP_URL/$PULP_DOMAIN/api/v3/content/deb/packages/" > "$UPLOAD_DIR/$i.task"
  ) &
  while (($(jobs -rp | wc -l) >= MAX_PARALLEL_UPLOADS)); do
    wait -n || true
  done
done
wait || true

echo "[INFO] Resolving ${#ORPHAN_FILES[@]} uploaded package(s)"
ORPHAN_SHA256S=()
for i in "${!ORPHAN_FILES[@]}"; do
  FILE=${ORPHAN_FILES[$i]}
  if [[ ! -s "$UPLOAD_DIR/$i.task" ]]; then
    echo "::error::Upload failed for $FILE (no task href, see the worker error above)"
    exit 1
  fi
  ORPHAN_SHA256S+=("$(sha256sum "$FILE" | cut -d' ' -f1)")
done
for i in "${!ORPHAN_FILES[@]}"; do
  if ((i % 40 == 0)); then
    refresh_pulp_token
  fi
  (
    resolve_task_content "$(cat "$UPLOAD_DIR/$i.task")" "packages" \
      "--data-urlencode sha256=${ORPHAN_SHA256S[$i]}" > "$UPLOAD_DIR/$i.content"
  ) &
  while (($(jobs -rp | wc -l) >= MAX_PARALLEL_UPLOADS)); do
    wait -n || true
  done
done
wait || true
PACKAGE_HREFS=()
for i in "${!ORPHAN_FILES[@]}"; do
  if [[ ! -s "$UPLOAD_DIR/$i.content" ]]; then
    echo "::error::Cannot resolve the uploaded content for ${ORPHAN_FILES[$i]} (see the worker error above)"
    exit 1
  fi
  PACKAGE_HREFS+=("$(cat "$UPLOAD_DIR/$i.content")")
done

# package_release_components create is synchronous (201 with the unit, no
# task); a failed create (already existing on a job re-run) falls back to a lookup
echo "[INFO] Associating ${#PACKAGE_HREFS[@]} package(s) with $SUITE/main"
for i in "${!PACKAGE_HREFS[@]}"; do
  if ((i % 40 == 0)); then
    refresh_pulp_token
  fi
  (
    refresh_pulp_token
    out=$(post_json "$PULP_URL/$PULP_DOMAIN/api/v3/content/deb/package_release_components/" \
      "{\"package\": \"${PACKAGE_HREFS[$i]}\", \"release_component\": \"$RELEASE_COMPONENT_HREF\"}") || out=$'\n000'
    code="${out##*$'\n'}"
    body="${out%$'\n'*}"
    href=""
    if [[ "$code" == 2* ]]; then
      # tolerate a non-json body (gateway error page behind a 2xx)
      href=$(echo "$body" | jq -r '.pulp_href // .task // empty' 2>/dev/null) || href=""
    fi
    if [[ -z "$href" ]]; then
      href=$(lookup_deb_content "package_release_components" \
        "--data-urlencode package=${PACKAGE_HREFS[$i]} --data-urlencode release_component=$RELEASE_COMPONENT_HREF")
      if [[ -n "$href" ]]; then
        # api answers 500 on a duplicate synchronous create; expected on a rerun
        echo "[INFO] ${ORPHAN_FILES[$i]}: suite association already exists (HTTP $code on create, expected on a rerun), reusing it"
      else
        echo "[WARN] Suite association create for ${ORPHAN_FILES[$i]} returned HTTP $code: $(echo "$body" | head -c 300)" >&2
      fi
    fi
    printf '%s' "$href" > "$UPLOAD_DIR/$i.prc"
  ) &
  while (($(jobs -rp | wc -l) >= MAX_PARALLEL_UPLOADS)); do
    wait -n || true
  done
done
wait || true

PRC_HREFS=()
for i in "${!PACKAGE_HREFS[@]}"; do
  href=""
  [[ -s "$UPLOAD_DIR/$i.prc" ]] && href=$(cat "$UPLOAD_DIR/$i.prc")
  if [[ "$href" == */tasks/* ]]; then
    href=$(resolve_task_content "$href" "package_release_components" \
      "--data-urlencode package=${PACKAGE_HREFS[$i]} --data-urlencode release_component=$RELEASE_COMPONENT_HREF")
  fi
  if [[ -z "$href" ]]; then
    echo "::error::Suite association failed for ${ORPHAN_FILES[$i]} (see the worker error above)"
    exit 1
  fi
  PRC_HREFS+=("$href")
done
rm -rf "$UPLOAD_DIR"

if ((${#PACKAGE_HREFS[@]} > 0)); then
  echo "[INFO] Adding ${#PACKAGE_HREFS[@]} package(s) and their suite associations to $REPOSITORY_NAME in a single task"
  refresh_pulp_token
  # body through a file: thousands of hrefs exceed the argv limit
  ADD_BODY_FILE=$(mktemp)
  printf '%s\n' "${PACKAGE_HREFS[@]}" "${PRC_HREFS[@]}" | jq -R . | jq -cs '{add_content_units: .}' > "$ADD_BODY_FILE"
  # retried like the legacy uploads: same repository-version race
  for modify_attempt in 1 2 3; do
    MODIFY_TASK=$(start_modify_task "$PULP_URL${REPOSITORY_HREF}modify/" "$ADD_BODY_FILE")
    wait_task_race "$MODIFY_TASK" && rc=0 || rc=$?
    if [[ $rc -eq 0 ]]; then
      break
    elif [[ $rc -eq 2 && $modify_attempt -lt 3 ]]; then
      echo "[WARN] Repository modify lost the repository-version race against a concurrent delivery, retrying"
      sleep $((modify_attempt * 15))
    else
      echo "::error::Repository modify failed"
      exit 1
    fi
  done
fi

record_and_publish
