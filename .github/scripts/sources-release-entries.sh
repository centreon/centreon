#!/usr/bin/env bash
set -euo pipefail

# Builds the download-site entries for one release bundle, from the source tarballs its component
# workflows published. Reading the published object back (rather than trusting what the build had)
# is what keeps the url and its checksum from ever disagreeing -- the same reason
# cma-release-entries.sh re-downloads its assets.
#
# Emits on stdout the TSV publish-download-release.sh consumes:
#   product  train  state  os  version  file  date  md5  size
#
# Everything is derived from the bundle tag and the component tags pointing at its commit. Nothing
# is read from the release branch: release-new deletes it right after tagging.

BUNDLE_TAG="${BUNDLE_TAG:?BUNDLE_TAG is not set}"
REPOSITORY="${REPOSITORY:-$GITHUB_REPOSITORY}"
WORKDIR="${WORKDIR:-$(mktemp -d)}"
REPORT_FILE="${REPORT_FILE:-sources-report.tsv}"
BUCKET_BASE_URL="${BUCKET_BASE_URL:-https://s3-eu-west-1.amazonaws.com/centreon-download/public}"

# a stable-tag component run is ~3.5 min end to end; the ceiling is for a congested runner pool
POLL_TIMEOUT="${POLL_TIMEOUT:-2700}"
POLL_INTERVAL="${POLL_INTERVAL:-20}"
POLL_STALL_ROUNDS="${POLL_STALL_ROUNDS:-6}"
# how long a component's workflow run may take to appear before it is treated as never coming
RUN_APPEAR_GRACE="${RUN_APPEAR_GRACE:-300}"

STATE="stable"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
source "$SCRIPT_DIR/release/release-source-products.sh"

die()  { echo "::error::sources-release-entries: $*" >&2; exit 1; }
warn() { echo "::warning::sources-release-entries: $*" >&2; }
info() { echo "[INFO] $*" >&2; }

for tool in gh jq curl md5sum; do
  command -v "$tool" >/dev/null 2>&1 || die "$tool is required but is not installed on this runner"
done

# ---------------------------------------------------------------------------
# Expected set: the component tags actually on the bundle commit
# ---------------------------------------------------------------------------
# git tag --points-at is ground truth. release-new computes its intended tag list before pushing
# and exits mid-loop if one already exists, so intent and reality can legitimately differ.
BUNDLE_SHA="$(git rev-parse -q --verify "${BUNDLE_TAG}^{commit}")" \
  || die "bundle tag '$BUNDLE_TAG' does not resolve; fetch tags first (fetch-depth: 0, fetch-tags: true)"

declare -a COMPONENTS=() VERSIONS=()
while read -r tag; do
  [[ -n "$tag" ]] || continue
  [[ "$tag" =~ ^(centreon-[a-z0-9-]+)-([0-9]+\.[0-9]+\.[0-9]+)$ ]] || continue
  component="${BASH_REMATCH[1]}"
  version="${BASH_REMATCH[2]}"
  if [[ -z "${SOURCE_BUCKET_DIRECTORY[$component]+x}" ]]; then
    info "skipping $tag (publishes no source tarball)"
    continue
  fi
  COMPONENTS+=("$component")
  VERSIONS+=("$version")
done < <(git tag --points-at "$BUNDLE_SHA")

(( ${#COMPONENTS[@]} > 0 )) \
  || die "no component tag publishing a source tarball points at $BUNDLE_TAG ($BUNDLE_SHA)"

# the train is the bundle tag's own suffix, not a component version: components version apart
[[ "$BUNDLE_TAG" =~ -([0-9]{2}\.[0-9]{2})$ ]] \
  || die "cannot read the train from bundle tag '$BUNDLE_TAG'"
TRAIN="${BASH_REMATCH[1]}"

info "${#COMPONENTS[@]} component(s) expected for $BUNDLE_TAG (train $TRAIN): ${COMPONENTS[*]}"

# ---------------------------------------------------------------------------
# Wait for each component's deliver-sources job to reach a terminal state
# ---------------------------------------------------------------------------
# Readiness comes from the Actions API, not from probing the object: a missing key on the public
# bucket answers 403, not 404, and cannot be told apart from a broken ACL or a mistyped key.
declare -A JOB_CONCLUSION=() JOB_URL=()

runs_json="$WORKDIR/runs.json"
api_err="$WORKDIR/gh-api.err"
api_ever_ok="false"
deadline=$(( SECONDS + POLL_TIMEOUT ))
poll_started=$SECONDS
stall_rounds=0
previous_resolved=-1

while true; do
  if ! gh api --paginate "repos/$REPOSITORY/actions/runs?head_sha=$BUNDLE_SHA&per_page=100" \
       > "$runs_json" 2>"$api_err"; then
    # a blip is worth retrying, but a bad token or a missing scope never resolves, and waiting
    # out the deadline would blame the component workflows for an API failure
    if [[ "$api_ever_ok" == "false" ]]; then
      cat "$api_err" >&2
      die "cannot list workflow runs for $BUNDLE_SHA: the Actions API call failed (see above)"
    fi
    warn "listing workflow runs failed, will retry: $(tail -1 "$api_err")"
  else
    api_ever_ok="true"
  fi

  resolved=0
  active=0
  for i in "${!COMPONENTS[@]}"; do
    component="${COMPONENTS[$i]}"
    tag="${component}-${VERSIONS[$i]}"
    [[ -n "${JOB_CONCLUSION[$component]:-}" ]] && { resolved=$((resolved + 1)); continue; }

    # head_branch alone is not trustworthy: a fork pull request is listed under this repository's
    # head_sha and carries a branch name its author chose, so it could impersonate a component tag
    # and either stall the poll or mask the real run.
    # -s: --paginate emits one JSON document per page, so without slurping the filter would run
    # once per page and could emit several rows where the parser below expects exactly one.
    run_line="$(jq -s -r --arg tag "$tag" --arg repo "$REPOSITORY" \
      '[.[].workflow_runs[]? | select(.head_branch == $tag and .event == "push"
                                      and .head_repository.full_name == $repo)]
       | sort_by(.created_at) | last | select(.) | [(.id|tostring), .status] | @tsv' \
      "$runs_json" 2>/dev/null || true)"
    # no run yet means the tag push has not been picked up, which is waiting, not stalling
    if [[ -z "$run_line" ]]; then
      # a run that never appears is not in flight; after the grace period stop counting it, or the
      # stall guard could never fire and the wait would always run to POLL_TIMEOUT
      if (( SECONDS < poll_started + RUN_APPEAR_GRACE )); then active=$((active + 1)); fi
      continue
    fi
    mapfile -t -d $'\t' rcols < <(printf '%s' "$run_line")
    run_id="${rcols[0]-}"; run_status="${rcols[1]-}"

    job="$(gh api --paginate "repos/$REPOSITORY/actions/runs/$run_id/jobs?per_page=100" 2>/dev/null \
      | jq -s -r '[.[].jobs[]? | select(.name == "deliver-sources")] | last
               | select(.) | [.status, (.conclusion // ""), .html_url] | @tsv' || true)"
    # a run still going can still grow the job; a finished run without it never will
    if [[ -z "$job" ]]; then
      [[ "$run_status" == "completed" ]] || active=$((active + 1))
      continue
    fi

    mapfile -t -d $'\t' jcols < <(printf '%s' "$job")
    j_status="${jcols[0]-}"; j_conclusion="${jcols[1]-}"; j_url="${jcols[2]-}"
    if [[ "$j_status" == "completed" ]]; then
      JOB_CONCLUSION[$component]="$j_conclusion"
      JOB_URL[$component]="$j_url"
      resolved=$((resolved + 1))
      info "$component: deliver-sources $j_conclusion"
    else
      active=$((active + 1))
    fi
  done

  (( resolved == ${#COMPONENTS[@]} )) && break

  # A queued or running job is progress, so only an idle wait counts as a stall. Components
  # reach deliver-sources through different dependency chains, so one finishing long before
  # another is normal; waiting that out is what POLL_TIMEOUT is for.
  if (( active == 0 && resolved == previous_resolved )); then
    stall_rounds=$(( stall_rounds + 1 ))
  else
    stall_rounds=0
  fi
  previous_resolved=$resolved

  if (( stall_rounds >= POLL_STALL_ROUNDS )); then
    warn "no job left running and no progress for $POLL_STALL_ROUNDS rounds; $resolved/${#COMPONENTS[@]} resolved, giving up early"
    break
  fi
  if (( SECONDS >= deadline )); then
    warn "timed out after ${POLL_TIMEOUT}s; $resolved/${#COMPONENTS[@]} resolved"
    break
  fi

  info "$resolved/${#COMPONENTS[@]} component(s) finished, waiting ${POLL_INTERVAL}s..."
  sleep "$POLL_INTERVAL"
done

# ---------------------------------------------------------------------------
# Read each published tarball back: size from the response, md5 from the bytes
# ---------------------------------------------------------------------------
: > "$REPORT_FILE"
EMITTED=0
MISSING=0

for i in "${!COMPONENTS[@]}"; do
  component="${COMPONENTS[$i]}"
  version="${VERSIONS[$i]}"
  conclusion="${JOB_CONCLUSION[$component]:-not finished}"
  bucket_dir="${SOURCE_BUCKET_DIRECTORY[$component]}"
  file="${component}-${version}.tar.gz"
  url="${BUCKET_BASE_URL}/${bucket_dir}/${file}"

  if [[ "$conclusion" != "success" ]]; then
    MISSING=$((MISSING + 1))
    printf '%s\t%s\t%s\t%s\t%s\n' \
      "$component" "$version" "missing" "$conclusion" "${JOB_URL[$component]:-}" >>"$REPORT_FILE"
    warn "$component $version: deliver-sources is '$conclusion', not publishing it"
    continue
  fi

  local_file="$WORKDIR/$file"
  # a green job whose object does not serve is a real error, not a slow one
  headers="$WORKDIR/headers.txt"
  curl -fsSL --proto '=https' --proto-redir '=https' --retry 3 --retry-all-errors \
    --connect-timeout 30 --speed-limit 1024 --speed-time 60 --max-time 1800 \
    -D "$headers" -o "$local_file" "$url" \
    || die "$component: deliver-sources succeeded but $url does not serve"

  actual_size="$(stat -c '%s' "$local_file")"
  # tolower(), not IGNORECASE: the latter is a gawk extension and mawk (the Ubuntu default)
  # silently matches nothing, which would kill this check and the date below without a word
  declared_size="$(awk 'tolower($0) ~ /^content-length:/{gsub(/\r/,""); print $2}' "$headers" | tail -1)"
  if [[ -n "$declared_size" && "$declared_size" != "$actual_size" ]]; then
    die "$file is $actual_size bytes but Content-Length announced $declared_size"
  fi
  md5="$(md5sum "$local_file" | cut -d' ' -f1)"
  rm -f "$local_file"

  # the publication date is the object's, not the run's, so a re-publication does not rewrite it
  date="$(awk 'tolower($0) ~ /^last-modified:/{sub(/^[^:]*: */,""); gsub(/\r/,""); print}' "$headers" | tail -1)"
  if [[ -n "$date" ]]; then
    date="$(date -u -d "$date" +%FT%TZ 2>/dev/null || true)"
  fi
  [[ -n "$date" ]] || date="$(date -u +%FT%TZ)"

  # os is empty for a tarball; the site derives the path from the product's bucket and base_url,
  # so file is a bare name. The trailing columns are the (unused) sidecar uri and the output file:
  # the target keys its release files by version, and components of one bundle version apart, so
  # one release legitimately writes several.
  printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n' \
    "$component" "$TRAIN" "$STATE" "" "$version" "$file" "$date" "$md5" "$actual_size" \
    "" "${version}.yaml"

  printf '%s\t%s\t%s\t%s\t%s\n' \
    "$component" "$version" "published" "$md5" "$actual_size" >>"$REPORT_FILE"
  EMITTED=$((EMITTED + 1))
done

(( EMITTED > 0 )) || die "no component of $BUNDLE_TAG published a usable tarball"

info "emitted $EMITTED entry(ies), $MISSING missing"
[[ -n "${GITHUB_OUTPUT:-}" ]] && {
  echo "emitted=$EMITTED" >>"$GITHUB_OUTPUT"
  echo "missing=$MISSING" >>"$GITHUB_OUTPUT"
}
exit 0
