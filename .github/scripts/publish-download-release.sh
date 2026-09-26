#!/usr/bin/env bash
# Publishes release metadata to download.centreon.com by committing a release
# YAML entry to centreon/WebApp-download and opening a PR against its default
# branch.
set -euo pipefail

readonly SCRIPT_NAME="${0##*/}"

WEBAPP_REPO="${WEBAPP_REPO:-centreon/WebApp-download}"
WEBAPP_BASE="${WEBAPP_BASE:-develop}"
ENTRIES_FILE=""
OUT_NAME=""
BRANCH=""
COMMIT_MESSAGE=""
PR_TITLE=""
PR_BODY_FILE=""
PR_LABEL="release"
DRY_RUN="false"
WORKDIR=""

log()      { printf '%s\n' "$*"; }
log_ok()   { printf '\033[0;32m✓\033[0m %s\n' "$*"; }
log_skip() { printf '\033[0;33m-\033[0m %s\n' "$*"; }
die()      { printf '\033[0;31m✗\033[0m %s\n' "$*" >&2; exit 1; }

cleanup() { [[ -n "$WORKDIR" && -d "$WORKDIR" ]] && rm -rf "$WORKDIR"; }
trap cleanup EXIT

# Recap of where the metadata went, so a run page shows the outcome without
# anyone reading the log. Falls back to stdout when run outside Actions.
write_summary() {
  local pr_state="$1"
  {
    echo "### Download site entry"
    echo ""
    echo "| | |"
    echo "|---|---|"
    echo "| Repository | \`${WEBAPP_REPO}\` (\`${WEBAPP_BASE}\`) |"
    echo "| File(s) | $(printf '`%s`, ' "${written_files[@]:-}" | sed 's/, $//') |"
    echo "| Entries | ${entry_count} |"
    echo "| Branch | \`${BRANCH}\` |"
    echo "| Pull request | ${pr_state} |"
    echo "| Validation | ${validate_line:-not run} |"
    echo ""
  } | tee -a "${GITHUB_STEP_SUMMARY:-/dev/null}"
}

usage() {
  cat <<EOF
Usage: $SCRIPT_NAME --entries FILE --out-name NAME [options]

Required:
  --entries FILE       TSV file, one release entry per line (see below)
  --out-name NAME      release YAML filename, e.g. 25.10-20260600-alma9.yaml
  --branch NAME        branch to create in $WEBAPP_REPO
  --commit-message MSG commit message
  --pr-title TITLE     pull request title

Optional:
  --pr-body-file FILE  pull request body (default: generated)
  --pr-label LABEL     label to apply (default: $PR_LABEL, "" to skip)
  --dry-run            print what would happen, mutate nothing
  --help

Entry TSV columns (tab-separated, no header):
  product  train  state  os  version  file  date  md5  size  [s3_uri]  [out_name]

  os may be empty. s3_uri is ignored (kept so one TSV shape serves every
  pipeline). out_name overrides --out-name for that entry. All entries must
  share one train.

Environment:
  WEBAPP_REPO   target repo (default: centreon/WebApp-download)
  WEBAPP_BASE   base branch  (default: develop)
  GH_TOKEN      token with contents:write and pull_requests:write on WEBAPP_REPO
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --entries)        ENTRIES_FILE="${2:?--entries needs a value}"; shift 2 ;;
    --out-name)       OUT_NAME="${2:?--out-name needs a value}"; shift 2 ;;
    --branch)         BRANCH="${2:?--branch needs a value}"; shift 2 ;;
    --commit-message) COMMIT_MESSAGE="${2:?--commit-message needs a value}"; shift 2 ;;
    --pr-title)       PR_TITLE="${2:?--pr-title needs a value}"; shift 2 ;;
    --pr-body-file)   PR_BODY_FILE="${2:?--pr-body-file needs a value}"; shift 2 ;;
    --pr-label)       PR_LABEL="${2?--pr-label needs a value (pass '' to skip labelling)}"; shift 2 ;;
    --dry-run)        DRY_RUN="true"; shift ;;
    --help|-h)        usage; exit 0 ;;
    *)                die "unknown option: $1 (see --help)" ;;
  esac
done

[[ -n "$ENTRIES_FILE" ]]    || die "--entries is required (see --help)"
[[ -f "$ENTRIES_FILE" ]]    || die "entries file not found: $ENTRIES_FILE"
# optional: an entry may carry its own out_name column instead (checked after parsing)
[[ -n "$BRANCH" ]]          || die "--branch is required"
[[ -n "$COMMIT_MESSAGE" ]]  || die "--commit-message is required"
[[ -n "$PR_TITLE" ]]        || die "--pr-title is required"
[[ -z "$OUT_NAME" || "$OUT_NAME" == *.yaml ]] || die "--out-name must end in .yaml: $OUT_NAME"
[[ "$OUT_NAME" != */* && "$OUT_NAME" != .* ]] || die "--out-name must be a bare filename: $OUT_NAME"

for tool in git curl; do
  command -v "$tool" >/dev/null 2>&1 || die "$tool is required but not installed"
done

# ---------------------------------------------------------------------------
# Parse and validate entries
# ---------------------------------------------------------------------------
# Parallel arrays; bash 4 has no array-of-struct. Index i is one release entry.
declare -a E_PRODUCT E_TRAIN E_STATE E_OS E_VERSION E_FILE E_DATE E_MD5 E_SIZE E_OUT_NAME
entry_count=0
# tracks whether any entry brought its own output filename; if none did, a single --out-name is in
# force and the old one-file-per-run invariant still has to hold
any_entry_out_name="false"
line_no=0

while IFS= read -r line || [[ -n "$line" ]]; do
  line_no=$((line_no + 1))
  if [[ -z "${line//[[:space:]]/}" || "$line" == \#* ]]; then
    continue
  fi

  # Tab counts as IFS whitespace, so `read -r a b c` collapses runs of tabs and
  # an empty os column would shift every later field. mapfile keeps empties.
  mapfile -t -d $'\t' cols < <(printf '%s' "$line")

  local_ctx="entry on line $line_no"
  [[ "${#cols[@]}" -ge 9 && "${#cols[@]}" -le 11 ]] \
    || die "$local_ctx: expected 9 to 11 tab-separated columns, got ${#cols[@]}"

  product="${cols[0]-}"; train="${cols[1]-}";   state="${cols[2]-}"
  os="${cols[3]-}";      version="${cols[4]-}"; file="${cols[5]-}"
  date="${cols[6]-}";    md5="${cols[7]-}";     size="${cols[8]-}"
  # One release can span several output files, because components version independently and the
  # target keys its files by version. Empty falls back to --out-name, which keeps single-file
  # callers (the OVA publisher) working unchanged.
  entry_out_name="${cols[10]-}"
  if [[ -n "$entry_out_name" ]]; then any_entry_out_name="true"; fi
  [[ -z "$entry_out_name" || "$entry_out_name" == *.yaml ]] \
    || die "$local_ctx: out_name must end in .yaml (got '$entry_out_name')"
  # it is joined into the output path, so a separator or a leading dot could escape the release dir
  [[ "$entry_out_name" != */* && "$entry_out_name" != .* ]] \
    || die "$local_ctx: out_name must be a bare filename (got '$entry_out_name')"

  [[ -n "$product" ]] || die "$local_ctx: product is required"
  [[ -n "$train"   ]] || die "$local_ctx: train is required"
  [[ -n "$version" ]] || die "$local_ctx: version is required"
  [[ -n "$file"    ]] || die "$local_ctx: file is required"
  [[ -n "$date"    ]] || die "$local_ctx: date is required (schema has no default)"
  # file and os are rendered into "..." scalars; these two characters are what could break out
  [[ "$file" != *'"'* && "$file" != *\\* ]] \
    || die "$local_ctx: file must not contain a quote or a backslash (got '$file')"
  [[ "$os" != *'"'* && "$os" != *\\* ]] \
    || die "$local_ctx: os must not contain a quote or a backslash (got '$os')"
  [[ "$version" != *'"'* && "$version" != *\\* ]] \
    || die "$local_ctx: version must not contain a quote or a backslash (got '$version')"

  # Mirror src/lib/schema.js: state enum, UTC date shape, md5 hex, integer size.
  case "${state:=stable}" in
    stable|rc|beta) ;;
    *) die "$local_ctx: state must be stable, rc or beta (got '$state')" ;;
  esac
  [[ "$date" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}(T[0-9]{2}:[0-9]{2}(:[0-9]{2})?Z)?$ ]] \
    || die "$local_ctx: date must be YYYY-MM-DD or YYYY-MM-DDTHH:MM:SSZ (got '$date')"
  [[ "$md5" =~ ^[0-9a-f]{32}$ ]] \
    || die "$local_ctx: md5 must be 32 lowercase hex chars (got '$md5')"
  [[ "$size" =~ ^[0-9]+$ ]] \
    || die "$local_ctx: size must be a non-negative integer in bytes (got '$size')"
  [[ "$size" -gt 0 ]] \
    || die "$local_ctx: size is 0 - refusing to publish an empty artifact"

  E_PRODUCT+=("$product"); E_TRAIN+=("$train");   E_STATE+=("$state")
  E_OS+=("$os");           E_VERSION+=("$version"); E_FILE+=("$file")
  E_DATE+=("$date");       E_MD5+=("$md5");       E_SIZE+=("$size")
  E_OUT_NAME+=("${entry_out_name:-$OUT_NAME}")
  entry_count=$((entry_count + 1))
done < "$ENTRIES_FILE"

[[ "$entry_count" -gt 0 ]] || die "no entries found in $ENTRIES_FILE"

train="${E_TRAIN[0]}"
for i in "${!E_TRAIN[@]}"; do
  [[ "${E_TRAIN[$i]}" == "$train" ]] \
    || die "all entries must share one train: '${E_TRAIN[$i]}' != '$train'"
done

log "→ $entry_count entry(ies) for train $train"

# ---------------------------------------------------------------------------
# Clone the target repo and run pre-flight checks against its catalog
# ---------------------------------------------------------------------------
WORKDIR="$(mktemp -d)"
repo_dir="$WORKDIR/webapp-download"

clone_url="https://github.com/${WEBAPP_REPO}.git"

# Authenticate with a per-invocation header instead of a credentialed remote
# URL: `git -c` before the subcommand is not persisted, so the token never
# lands in .git/config, which is the cwd the target repo's own code runs in.
# core.hooksPath=/dev/null unconditionally: the target repository's own code runs inside this
# clone, and a hook it dropped would execute on our commit and push with the auth header in
# GIT_CONFIG_PARAMETERS, which git hands to every child.
git_auth=(-c core.hooksPath=/dev/null)
if [[ -n "${GH_TOKEN:-}" ]]; then
  auth_basic="$(printf 'x-access-token:%s' "$GH_TOKEN" | base64 -w0)"
  # Actions masks the literal secret, not anything derived from it, so register
  # the encoded form too: it is reversible back to a working token.
  if [[ -n "${GITHUB_ACTIONS:-}" ]]; then
    echo "::add-mask::$auth_basic"
  fi
  auth_header="Authorization: Basic $auth_basic"
  # Scoped to this url, not global: http.extraHeader is sent to whatever host git contacts, so
  # a rewritten remote would hand the credential to it. Scoped, another host gets nothing.
  git_auth+=(-c "http.${clone_url}.extraHeader=$auth_header")
fi
authed_git() { git "${git_auth[@]}" "$@"; }

# A missing repo grant on a private repo answers 404, not 403 - say so plainly.
if ! authed_git clone --quiet --depth 1 --branch "$WEBAPP_BASE" "$clone_url" "$repo_dir" 2>"$WORKDIR/clone.err"; then
  if grep -qiE 'not found|could not read' "$WORKDIR/clone.err"; then
    die "cannot clone ${WEBAPP_REPO}@${WEBAPP_BASE}. On a private repo a missing write grant reports 'not found', not a permission error - check the token's access to ${WEBAPP_REPO}."
  fi
  cat "$WORKDIR/clone.err" >&2
  die "cannot clone ${WEBAPP_REPO}@${WEBAPP_BASE}"
fi
log_ok "cloned ${WEBAPP_REPO}@${WEBAPP_BASE}"

# Set on the clone itself, not just our own git invocations: the commit below is a plain git
# call, and hooks are what a plain call would pick up from this tree.
git -C "$repo_dir" config core.hooksPath /dev/null

# Reuse an open release branch so each per-OS run of one build accumulates into
# the same file and PR. (The site's newest-build-only rule is appliances-only, so a partial
# publication here simply shows fewer rows rather than hiding ones already published.)
# Asked from inside the fresh clone, not the caller's cwd: actions/checkout leaves a url-scoped
# http.https://github.com/.extraheader in the calling repository's config, and that ambient
# credential competes with ours. Errors are reported, never swallowed: "absent" (exit 2) and
# "could not ask" must not look the same, or the per-OS runs stop accumulating into one branch.
branch_exists="false"
ls_remote_err="$WORKDIR/ls-remote.err"
if authed_git -C "$repo_dir" ls-remote --exit-code --heads origin "$BRANCH" >/dev/null 2>"$ls_remote_err"; then
  branch_exists="true"
elif [[ $? -ne 2 ]]; then
  cat "$ls_remote_err" >&2
  die "cannot tell whether $BRANCH already exists on ${WEBAPP_REPO}. Refusing to continue: creating it blindly would fail the push if it is already there."
fi

if [[ "$branch_exists" == "true" ]]; then
  authed_git -C "$repo_dir" fetch --quiet --depth 1 origin "$BRANCH" \
    || die "branch $BRANCH exists on ${WEBAPP_REPO} but could not be fetched"
  authed_git -C "$repo_dir" checkout --quiet -B "$BRANCH" FETCH_HEAD
  log_ok "reusing existing branch $BRANCH"
else
  log "→ $BRANCH does not exist yet on ${WEBAPP_REPO}, it will be created"
fi

# Captured before any code from the target repository runs, so it is the honest base for the
# commit this run builds.
base_sha="$(git -C "$repo_dir" rev-parse HEAD)"

catalog="$repo_dir/src/data/catalog.yaml"
[[ -f "$catalog" ]] || die "$catalog missing - the target repo layout changed"

# Reads catalog.yaml with sed rather than a YAML parser: only flat product keys
# and version ids are needed, and this keeps the script dependency-free.
catalog_products() {
  sed -n '/^products:/,/^[a-z_]*:/p' "$catalog" | sed -n 's/^  \([a-z0-9][a-z0-9._-]*\):[[:space:]]*$/\1/p'
}
catalog_trains() {
  sed -n '/^versions:/,/^[a-z_]*:/p' "$catalog" | sed -n 's/.*id:[[:space:]]*"\([^"]*\)".*/\1/p'
}
product_group() {
  sed -n "/^  $1:\$/,/^  [a-z0-9][a-z0-9._-]*:\$/p" "$catalog" \
    | sed -n 's/^    group:[[:space:]]*"\?\([a-z]*\)"\?[[:space:]]*$/\1/p' | head -1
}

known_trains="$(catalog_trains)"
grep -qxF "$train" <<<"$known_trains" || die \
  "train '$train' is not declared in ${WEBAPP_REPO} src/data/catalog.yaml (versions:). Adding a train is a human decision - open a catalog PR first. Known trains: $(tr '\n' ' ' <<<"$known_trains")"

known_products="$(catalog_products)"
# Each entry resolves its own output file: the group comes from the product, the filename from the
# entry. Entries of one run may therefore legitimately land in different files and different group
# directories - a release spans packages/ and widgets/ whenever it ships a widget alongside web.
declare -a E_OUT_REL
declare -A seen_groups=()
for i in "${!E_PRODUCT[@]}"; do
  p="${E_PRODUCT[$i]}"
  grep -qxF "$p" <<<"$known_products" || die \
    "product '$p' is not declared in ${WEBAPP_REPO} src/data/catalog.yaml (products:). Adding a product is a human decision - open a catalog PR first."
  g="$(product_group "$p")"
  [[ -n "$g" ]] || die "product '$p' has no group in catalog.yaml"

  # The site tab enum and the on-disk folder differ for widgets only.
  case "$g" in
    appliances|packages|agent) g_dir="$g" ;;
    custom)                    g_dir="widgets" ;;
    *) die "unhandled product group '$g' for '$p' - teach this script its folder" ;;
  esac

  [[ -n "${E_OUT_NAME[$i]}" ]] || die "entry $i ($p) has no output filename: pass --out-name or an out_name column"
  E_OUT_REL[$i]="src/data/releases/${g_dir}/${train}/${E_OUT_NAME[$i]}"
  seen_groups[$g_dir]=1
done

mapfile -t OUT_RELS < <(printf '%s\n' "${E_OUT_REL[@]}" | LC_ALL=C sort -u)

# With a single --out-name and no per-entry out_name there is nothing to separate products of
# different groups, so entries would silently land in another group's directory. This is the
# pre-multi-file "all entries must share one product group" guard, kept for single-file callers.
if [[ "$any_entry_out_name" == "false" && "${#OUT_RELS[@]}" -gt 1 ]]; then
  die "entries resolve to ${#OUT_RELS[@]} output files (${OUT_RELS[*]}) but only one --out-name was given, so they do not share a product group. Give each entry its own out_name column to publish across groups."
fi
log_ok "catalog pre-flight passed (train $train, group(s) ${!seen_groups[*]}, ${#OUT_RELS[@]} file(s))"

# ---------------------------------------------------------------------------
# Emit the release YAML
# ---------------------------------------------------------------------------
# Key order and quoting match scripts/rm-add-vm.mjs so generated files are
# indistinguishable from release-manager output.
render_entry() {
  local i="$1"
  printf -- '- product: "%s"\n' "${E_PRODUCT[$i]}"
  printf -- '  train: "%s"\n'   "${E_TRAIN[$i]}"
  printf -- '  state: "%s"\n'   "${E_STATE[$i]}"
  printf -- '  os: "%s"\n'      "${E_OS[$i]}"
  printf -- '  version: "%s"\n' "${E_VERSION[$i]}"
  printf -- '  file: "%s"\n'    "${E_FILE[$i]}"
  printf -- '  date: "%s"\n'    "${E_DATE[$i]}"
  printf -- '  md5: "%s"\n'     "${E_MD5[$i]}"
  printf -- '  size: %s\n'      "${E_SIZE[$i]}"
  printf -- '  enabled: true\n'
}

# Merge rather than truncate, the way rm-add-vm.mjs does: one file per build can be written by
# several per-OS runs, and a re-run must replace its own rows instead of duplicating them.
#
# Keyed on product+os+VERSION. product+os alone is not an identity: the monitoring agent ships
# amd64 and arm64 under one os, and keying on the pair silently dropped one of them. Version is
# safe to add because it carries the disambiguating axis (the os for appliances, the arch for the
# agent). The consequence to know: an existing row is replaced only when all three match, so if a
# version format ever changes while the output filename stays the same, old rows are kept rather
# than replaced -- the duplicate assertion below is what catches that.
# quote-agnostic: an existing entry may be single-quoted or unquoted and still be valid YAML;
# reading it as empty made it invisible to the supersede check and produced a duplicate row
yaml_scalar() { sed -n "s/^$2[[:space:]]*[\"']\\?\\(.*[^\"']\\)[\"']\\?[[:space:]]*$/\\1/p" "$1" | head -1; }

# Written once per output file. A release spans several when its components carry different
# version numbers, which is the normal case: web 25.10.16 and open-tickets 25.10.10 ship together
# but the target keys its files by version.
write_output_file() {
  local out_rel="$1"; shift
  local -a idx=("$@")
  local out_path="$repo_dir/$out_rel"

  local chunk_dir
  chunk_dir="$WORKDIR/chunks/$(tr / _ <<<"$out_rel")"
  mkdir -p "$chunk_dir"
  local -A chunk_seen=()
  # set here, not only inside the merge branch: a first publication keeps nothing
  local kept=0
  if [[ -f "$out_path" ]]; then
    # the chunker starts at the first "- ", so anything above it would be dropped on rewrite
    if head -1 "$out_path" | grep -qv '^- '; then
      [[ -z "$(head -1 "$out_path" | tr -d '[:space:]')" ]] \
        || die "$out_rel starts with $(head -1 "$out_path") rather than an entry; rewriting it would drop that line. Update this script for the target repo's new file shape."
    fi
    awk -v dir="$chunk_dir" '
      /^- / { n++; f = sprintf("%s/%04d.existing", dir, n) }
      n     { print > f }
    ' "$out_path"
    for chunk in "$chunk_dir"/*.existing; do
      [[ -e "$chunk" ]] || continue
      c_product="$(yaml_scalar "$chunk" '- product:')"
      c_os="$(yaml_scalar "$chunk" '  os:')"
      c_version="$(yaml_scalar "$chunk" '  version:')"
      local superseded="false"
      for i in "${idx[@]}"; do
        if [[ "$c_product" == "${E_PRODUCT[$i]}" && "$c_os" == "${E_OS[$i]}" && "$c_version" == "${E_VERSION[$i]}" ]]; then
          superseded="true"
          break
        fi
      done
      if [[ "$superseded" == "true" ]]; then
        rm -f "$chunk"
      else
        mv "$chunk" "${chunk%.existing}.keep"
        kept=$((kept + 1))
      fi
    done
    log "→ merging into an existing ${out_rel##*/} ($kept entry(ies) kept)"
  fi

  for i in "${idx[@]}"; do
    chunk_key="$(printf '%s|%s|%s' "${E_PRODUCT[$i]}" "${E_OS[$i]}" "${E_VERSION[$i]}")"
    # Two entries sharing a key silently truncate one into the other. (product, os) is unique for
    # today's appliances but is an assumption, not a guarantee: the monitoring agent hit exactly this
    # and lost 5 of 14 rows -- valid file, passing validation, missing data.
    [[ -z "${chunk_seen[$chunk_key]:-}" ]] \
      || die "two entries render to the same key '$chunk_key' - one would overwrite the other. Give them distinct versions, or extend the key."
    chunk_seen[$chunk_key]=1
    render_entry "$i" >"$chunk_dir/${chunk_key}.new"
  done

  mkdir -p "$(dirname "$out_path")"
  : >"$out_path"
  # Sort by the rendered product, os then version, matching rm-add-vm.mjs's chunk sort.
  while IFS= read -r chunk; do
    cat "$chunk" >>"$out_path"
  done < <(
    for chunk in "$chunk_dir"/*.keep "$chunk_dir"/*.new; do
      [[ -e "$chunk" ]] || continue
      printf '%s\t%s\t%s\t%s\n' \
        "$(yaml_scalar "$chunk" '- product:')" \
        "$(yaml_scalar "$chunk" '  os:')" \
        "$(yaml_scalar "$chunk" '  version:')" \
        "$chunk"
    done | LC_ALL=C sort -t$'\t' -k1,1 -k2,2 -k3,3 | cut -f4
  )

  # Belt and braces: whatever the keying, the file must hold every kept and every new entry.
  local written; written=$(grep -c '^- product:' "$out_path" || true)
  local expected=$((kept + ${#idx[@]}))
  [[ "$written" -eq "$expected" ]] \
    || die "$out_rel holds $written entry(ies) but $expected were expected ($kept kept + ${#idx[@]} new). Either entries were lost while assembling the file, or an existing entry does not start with '- product:' and the extractor needs updating for the target repo's rendering."

  # the count alone cannot see a kept entry duplicating a new one, which is what a mis-read
  # existing row produces; assert the identity is unique in the file that will be committed
  local dupes; dupes=$(awk '
    /^- product:/ { p = $0 } /^  os:/ { o = $0 } /^  version:/ { print p "|" o "|" $0 }
  ' "$out_path" | sort | uniq -d | head -3)
  [[ -z "$dupes" ]] || die "$out_rel would hold duplicate entries: $dupes"

  log_ok "wrote $out_rel ($written entry(ies))"
  log "--- $out_rel ---"
  cat "$out_path"
  log "--- end ---"
}

written_files=()
for out_rel in "${OUT_RELS[@]}"; do
  idx=()
  for i in "${!E_OUT_REL[@]}"; do
    [[ "${E_OUT_REL[$i]}" == "$out_rel" ]] && idx+=("$i")
  done
  write_output_file "$out_rel" "${idx[@]}"
  written_files+=("$out_rel")
done

# ---------------------------------------------------------------------------
# Gate on pnpm validate
# ---------------------------------------------------------------------------
# The target repo runs validate only in a push-triggered workflow that is not a
# required check, so this is the real gate. Prefer local node, else a container.

# pnpm 11's binary links against libatomic.so.1, which is absent from slim
# images and some runners; the target repo's own workflows install it too.
ensure_libatomic() {
  ldconfig -p 2>/dev/null | grep -q 'libatomic\.so\.1' && return 0
  command -v apt-get >/dev/null 2>&1 || return 1
  sudo -n apt-get update -qq >/dev/null 2>&1 || return 1
  sudo -n apt-get install -y -qq libatomic1 >/dev/null 2>&1
}

validate_catalog() {
  command -v pnpm >/dev/null 2>&1 || { echo "pnpm is not on PATH"; return 1; }
  local pnpm_major
  pnpm_major="$(pnpm --version 2>/dev/null | cut -d. -f1)"
  [[ "$pnpm_major" =~ ^[0-9]+$ ]] || { echo "cannot read the pnpm version"; return 1; }
  [[ "$pnpm_major" -ge 11 ]] || { echo "pnpm $pnpm_major is older than the required 11"; return 1; }
  ensure_libatomic || echo "warning: libatomic1 is missing and could not be installed; pnpm may fail to start"
  # Validate a throwaway copy, never the tree that gets committed. scripts/validate.mjs and any
  # .pnpmfile.cjs are the target repository's own code: run in $repo_dir they could rewrite the
  # YAML after its integrity checks, or drop a git hook. Dropping .git also keeps the install out
  # of what we push. --ignore-scripts and unsetting the tokens are kept, but neither is a boundary:
  # a .pnpmfile.cjs still runs, and a child can read an ancestor's environ through /proc.
  local tree="$WORKDIR/validate-tree"
  rm -rf "$tree"
  cp -a "$repo_dir" "$tree" || { echo "could not copy the clone for validation"; return 1; }
  rm -rf "$tree/.git"
  (
    cd "$tree"
    env -u GH_TOKEN -u GITHUB_TOKEN pnpm install --frozen-lockfile --ignore-scripts \
      --config.trustPolicy=no-downgrade \
      --config.minimumReleaseAge=2880 \
      --config.blockExoticSubdeps=true || exit 1
    env -u GH_TOKEN -u GITHUB_TOKEN node scripts/validate.mjs
  )
}

# The validator is the target repository's own code. It runs from a sibling directory, so it can
# still reach this tree; pin what we wrote and re-check it before staging, so a rewrite between
# validation and commit cannot reach the pull request.
# Held in a variable, not a file: WORKDIR is the validator's own parent directory, so a manifest
# written there could be rewritten to match a tampered tree. Process memory it cannot reach.
tree_manifest="$( cd "$repo_dir" && sha256sum "${written_files[@]:-}" )"

log "→ running pnpm validate"
validate_out="$WORKDIR/validate.log"
if ! validate_catalog >"$validate_out" 2>&1; then
  cat "$validate_out" >&2
  die "pnpm validate failed - nothing was pushed. This needs pnpm >=11 on PATH (the calling action provisions it via pnpm/action-setup) and the libatomic1 package."
fi

grep -q 'Catalogue valide' "$validate_out" \
  || { cat "$validate_out" >&2; die "pnpm validate did not report a valid catalog"; }
validate_line="$(grep 'Catalogue valide' "$validate_out" | head -1 | sed 's/^[^A-Za-z]*//')"
log_ok "$validate_line"

# ---------------------------------------------------------------------------
# Commit, push, open the PR
# ---------------------------------------------------------------------------
if [[ "$DRY_RUN" == "true" ]]; then
  log_skip "[dry-run] would create branch : $BRANCH"
  log_skip "[dry-run] would commit        : $COMMIT_MESSAGE"
  log_skip "[dry-run] would open PR       : $PR_TITLE"
  for f in "${written_files[@]:-}"; do
    log_skip "[dry-run] would target        : ${WEBAPP_REPO} ${WEBAPP_BASE} <- $f"
  done
  write_summary "not opened (dry run)"
  log_ok "dry run complete, nothing pushed"
  exit 0
fi

[[ -n "${GH_TOKEN:-}" ]] || die "GH_TOKEN is required to push and open a PR"
command -v gh >/dev/null 2>&1 || die "gh cli is required to open the pull request"

cd "$repo_dir"

# The target ruleset sets require_extra_approval_for_unattributed_changes, so
# the committer has to resolve to the token's own account. Ask the API rather
# than hardcode it, since a GitHub App token commits under a different identity.
commit_name="${GIT_AUTHOR_NAME:-}"
commit_email="${GIT_AUTHOR_EMAIL:-}"
if [[ -z "$commit_name" ]]; then
  if identity="$(gh api user --jq '"\(.login) \(.id)"' 2>/dev/null)" && [[ -n "$identity" ]]; then
    commit_name="${identity%% *}"
    commit_email="${identity##* }+${commit_name}@users.noreply.github.com"
  else
    # An App installation token cannot read /user; fall back to its bot identity.
    commit_name="github-actions[bot]"
    commit_email="41898282+github-actions[bot]@users.noreply.github.com"
  fi
fi
git config user.name  "$commit_name"
git config user.email "$commit_email"
log "→ committing as $commit_name <$commit_email>"

if [[ "$branch_exists" != "true" ]]; then
  authed_git checkout --quiet -b "$BRANCH"
fi
git add "${written_files[@]:-}"

if git diff --cached --quiet; then
  write_summary "not opened (no change)"
  log_skip "no change to commit - the ${#written_files[@]} file(s) already match ${WEBAPP_BASE}"
  exit 0
fi

authed_git commit --quiet -m "$COMMIT_MESSAGE"
# Verify what will actually be pushed. The commit is built from the index, not the working
# tree, so a pre-staged path, an extra commit, a clean filter or a symlink would all pass a
# check of the files on disk and still change what lands in the pull request.
# GIT_NO_REPLACE_OBJECTS: refs/replace/* can map the committed blob to a clean one, so every
# check below would read content that is not what gets pushed.
export GIT_NO_REPLACE_OBJECTS=1
[[ -z "$(git for-each-ref --format="%(refname)" "refs/replace/*")" ]] \
  || die "the clone carries replace refs; refusing to publish from it"
[[ ! -e "$(git rev-parse --git-path info/grafts)" ]] \
  || die "the clone carries grafts; refusing to publish from it"

parent="$(git rev-parse HEAD^)"
[[ "$parent" == "$base_sha" ]] \
  || die "the branch carries history this run did not create; refusing to push it"

mapfile -t committed < <(git diff --name-only "$base_sha" HEAD | LC_ALL=C sort)
mapfile -t intended  < <(printf '%s\n' "${written_files[@]:-}" | LC_ALL=C sort)
[[ "${committed[*]}" == "${intended[*]}" ]] \
  || die "the commit touches files this run did not write: ${committed[*]}"

while read -r want path; do
  [[ -n "${path:-}" ]] || continue
  [[ "$(git show "HEAD:$path" | sha256sum | cut -d' ' -f1)" == "$want" ]] \
    || die "$path was committed with content the run did not validate"
done <<<"$tree_manifest"
verified_sha="$(git rev-parse HEAD)"
log_ok "commit verified against what was validated"

# A rewritten remote or an insteadOf rule would redirect the push, so check both, then push the
# verified object id to the literal url rather than re-resolving origin and HEAD.
[[ "$(git config --get remote.origin.url || true)" == "$clone_url" ]] \
  || die "the clone's origin no longer points at ${WEBAPP_REPO}; refusing to push"
[[ -z "$(git config --get remote.origin.pushurl || true)" ]] \
  || die "the clone has a separate push url; refusing to push"
[[ -z "$(git config --get-regexp '^url\.' || true)" ]] \
  || die "the clone rewrites urls through insteadOf; refusing to push"

authed_git push --quiet "$clone_url" "${verified_sha}:refs/heads/$BRANCH" \
  || die "could not push $BRANCH to ${WEBAPP_REPO}. If another run advanced the same branch, re-run this job: it merges into whatever is there."
log_ok "pushed $BRANCH"

if [[ -z "$PR_BODY_FILE" ]]; then
  PR_BODY_FILE="$WORKDIR/pr-body.md"
  {
    printf '## Summary\n'
    printf -- '- Adds %d release entry(ies) for train `%s`\n' "$entry_count" "$train"
    for f in "${written_files[@]:-}"; do
      printf -- '  - `%s`\n' "$f"
    done
    printf -- '- md5 and size are recomputed by the publishing pipeline, not read from the S3 ETag\n'
    if [[ -n "${GITHUB_SERVER_URL:-}" && -n "${GITHUB_REPOSITORY:-}" && -n "${GITHUB_RUN_ID:-}" ]]; then
      printf -- '- Produced by [run %s](%s/%s/actions/runs/%s) - see its summary for the files, size and md5\n' \
        "$GITHUB_RUN_ID" "$GITHUB_SERVER_URL" "$GITHUB_REPOSITORY" "$GITHUB_RUN_ID"
    fi
    printf '\n## Test plan\n'
    printf -- '- [x] `pnpm validate` passed in the publishing pipeline before this PR was opened\n'
    printf -- '- [ ] Preview build shows the new rows for train `%s`\n' "$train"
  } >"$PR_BODY_FILE"
fi

export GH_TOKEN
# A second per-OS run of the same build pushes to a branch that already has a
# PR; update it instead of failing on create.
pr_url="$(gh pr list --repo "$WEBAPP_REPO" --head "$BRANCH" --state open \
  --json url --jq '.[0].url // empty' 2>/dev/null || true)"
if [[ -n "$pr_url" ]]; then
  log_ok "updated existing $pr_url"
else
  pr_url="$(gh pr create \
    --repo "$WEBAPP_REPO" \
    --base "$WEBAPP_BASE" \
    --head "$BRANCH" \
    --title "$PR_TITLE" \
    --body-file "$PR_BODY_FILE")"
  log_ok "opened $pr_url"
fi

if [[ -n "$PR_LABEL" ]]; then
  gh pr edit "$pr_url" --add-label "$PR_LABEL" >/dev/null 2>&1 \
    || log_skip "could not apply label '$PR_LABEL' (it may not exist in ${WEBAPP_REPO})"
fi

write_summary "$pr_url"
log_ok "release metadata published - a human review and squash merge are required"
