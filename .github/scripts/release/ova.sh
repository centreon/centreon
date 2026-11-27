#!/usr/bin/env bash
# ova.sh - Create the WebApp-download catalog PR for an OVA appliance build,
# or promote an already-merged one to production.
# Usage: ./ova.sh [--promote]
#
# With no flag, asks interactively which of the two to do. Pass --promote to
# skip straight to the promote flow (useful for re-running without the menu).
#
# 1) Create/update catalog PR: starts from two already-completed
# centreon-ova-publisher runs (one per OS, alma9 + deb12) in
# centreon/centreon-images, and creates (or updates) a PR on
# centreon/WebApp-download adding the 4-entry release YAML (centreon-vbox-vm +
# centreon-vmware-vm, x alma9 + deb12). Does not trigger centreon-ova-publisher
# itself - run that manually first (or point at an existing run after a
# rebuild).
#
# Idempotent on the deterministic branch name release-ova-<train>-<release>:
#   - no branch/PR yet             -> create fresh
#   - open PR already exists       -> push a corrective commit (rebuild case)
#   - already merged on develop    -> open a new correction PR
#
# 2) Promote: once the catalog PR above has been reviewed and merged, tags the
# current develop HEAD as the next vX.Y.Z and pushes it, which triggers
# deploy-prod.yml and deploys directly to download.centreon.com. Run this as
# a separate step after the merge - it does not create/merge the PR itself.

set -euo pipefail
trap 'echo ""; echo "ERROR at line $LINENO — exiting." >&2' ERR

promote=0
promote_explicit=0
while (($# > 0)); do
    case $1 in
        --promote)
            promote=1
            promote_explicit=1
            shift
            ;;
        -h|--help)
            sed -n '2,24p' "$0"
            exit 0
            ;;
        *)
            shift
            ;;
    esac
done

TODAY=$(date +%Y-%m-%d)
TMPFILE=$(mktemp)
CONTENTFILE=$(mktemp)
trap 'rm -f "${TMPFILE}" "${CONTENTFILE}"' EXIT

RED="\033[31m"
GREEN="\033[32m"
YELLOW="\033[33m"
BOLD="\033[1m"
RESET="\033[0m"

PUBLISHER_REPO="centreon/centreon-images"
CATALOG_REPO="centreon/WebApp-download"
CATALOG_BASE_BRANCH="develop"

# ── Promotion to production ───────────────────────────────────────────────────
# Tags are lightweight (matching the existing v0.1.x history) and created via
# the GitHub API rather than local `git tag`, so this works regardless of any
# local tag.forceSignAnnotated/tag.gpgSign config requiring a signing key.

# Sets globals LATEST_TAG / SUGGESTED_TAG (same no-subshell pattern as
# extract_run above) rather than returning via command substitution.
resolve_tags() {
    local latest
    latest=$(gh api "repos/${CATALOG_REPO}/tags" --paginate --jq '.[].name' \
        | grep -E '^v[0-9]+\.[0-9]+\.[0-9]+$' | sort -V | tail -1)
    if [[ -z "${latest}" ]]; then
        LATEST_TAG="(none)"
        SUGGESTED_TAG="v0.0.1"
        return
    fi
    local rest major minor patch
    rest="${latest#v}"
    major="${rest%%.*}"
    rest="${rest#*.}"
    minor="${rest%%.*}"
    patch="${rest#*.}"
    LATEST_TAG="${latest}"
    SUGGESTED_TAG="v${major}.${minor}.$((patch + 1))"
}

promote_to_prod() {
    printf "${BOLD}=== Promote %s (%s) to production ===${RESET}\n" "${CATALOG_REPO}" "${CATALOG_BASE_BRANCH}"

    echo ""
    echo "Reading ${CATALOG_BASE_BRANCH} HEAD..."
    local develop_sha last_msg tag_name
    develop_sha=$(gh api "repos/${CATALOG_REPO}/git/refs/heads/${CATALOG_BASE_BRANCH}" --jq '.object.sha')
    last_msg=$(gh api "repos/${CATALOG_REPO}/commits/${develop_sha}" --jq '.commit.message' | head -1)
    printf "  HEAD:         %s\n" "${develop_sha:0:7}"
    printf "  Last commit:  %s\n" "${last_msg}"

    resolve_tags
    printf "  Latest tag:   %s\n" "${LATEST_TAG}"

    echo ""
    read -rp "Tag to create and push [${SUGGESTED_TAG}]: " tag_name
    tag_name="${tag_name//$'\r'/}"
    tag_name="${tag_name:-${SUGGESTED_TAG}}"

    if [[ ! "${tag_name}" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        echo "Error: tag must match vX.Y.Z (e.g. ${SUGGESTED_TAG})." >&2
        exit 1
    fi

    if gh api "repos/${CATALOG_REPO}/git/refs/tags/${tag_name}" > /dev/null 2>&1; then
        echo "Error: tag ${tag_name} already exists on ${CATALOG_REPO}." >&2
        exit 1
    fi

    echo ""
    printf "${YELLOW}This pushes tag %s -> %s, which triggers deploy-prod.yml and deploys\n" "${tag_name}" "${develop_sha:0:7}"
    printf "DIRECTLY to download.centreon.com.${RESET}\n"
    read -rp "Proceed? (Y/n): " confirm_input
    confirm_input="${confirm_input//$'\r'/}"
    if [[ -n "${confirm_input}" && ! "${confirm_input}" =~ ^[Yy]$ ]]; then
        echo "Aborted. No tag created."
        exit 0
    fi

    echo ""
    echo "Creating tag ${tag_name}..."
    jq -n --arg ref "refs/tags/${tag_name}" --arg sha "${develop_sha}" '{"ref":$ref,"sha":$sha}' > "${TMPFILE}"
    gh api "repos/${CATALOG_REPO}/git/refs" --method POST --input "${TMPFILE}" > /dev/null

    echo "Tag pushed — waiting for deploy-prod to start..."
    sleep 5
    local run_id
    run_id=$(gh run list --repo "${CATALOG_REPO}" --workflow deploy-prod.yml \
        --json databaseId,headBranch --jq "[.[] | select(.headBranch == \"${tag_name}\")][0].databaseId // empty")

    if [[ -z "${run_id}" ]]; then
        printf "${YELLOW}Could not find the deploy-prod run yet — check manually:${RESET}\n"
        printf "  https://github.com/%s/actions/workflows/deploy-prod.yml\n" "${CATALOG_REPO}"
        return
    fi

    if gh run watch "${run_id}" --repo "${CATALOG_REPO}" --exit-status; then
        printf "\n${GREEN}Deployed — https://download.centreon.com${RESET}\n"
    else
        printf "\n${RED}deploy-prod failed — https://github.com/%s/actions/runs/%s${RESET}\n" "${CATALOG_REPO}" "${run_id}"
    fi
}

if ((!promote_explicit)); then
    echo "What do you want to do?"
    echo "  1) Create/update the WebApp-download catalog PR"
    echo "  2) Promote develop to production (create + push tag)"
    read -rp "Choice [1]: " menu_choice
    menu_choice="${menu_choice//$'\r'/}"
    menu_choice="${menu_choice:-1}"
    if [[ "${menu_choice}" == "2" ]]; then
        promote=1
    fi
fi

if ((promote)); then
    promote_to_prod
    exit 0
fi

echo "=== Publish OVA to download.centreon.com ==="

# ── Step 1: Inputs ────────────────────────────────────────────────────────────
echo ""
read -rp "Train (e.g. 24.10): " train
train="${train//$'\r'/}"
read -rp "Release number (e.g. 20260700): " release_number
release_number="${release_number//$'\r'/}"
read -rp "ova-publisher run ID for alma9: " run_id_alma9
run_id_alma9="${run_id_alma9//$'\r'/}"
read -rp "ova-publisher run ID for deb12: " run_id_deb12
run_id_deb12="${run_id_deb12//$'\r'/}"

# ── Step 2: Extract md5/size from each run ────────────────────────────────────
extract_run() {
    local run_id="$1"
    local expected_os="$2"

    local job_json
    job_json=$(gh run view "${run_id}" --repo "${PUBLISHER_REPO}" --json jobs \
        --jq '.jobs[] | select(.name == "Publish the OVA")')
    if [[ -z "${job_json}" ]]; then
        echo "Error: could not find the 'Publish the OVA' job on run ${run_id}." >&2
        exit 1
    fi

    local job_id transfer_ok
    job_id=$(jq -r '.databaseId' <<< "${job_json}")
    transfer_ok=$(jq -r '.steps[] | select(.name == "Transfer OVA") | .conclusion' <<< "${job_json}")

    if [[ "${transfer_ok}" != "success" ]]; then
        printf "Error: 'Transfer OVA' did not succeed on run %s (conclusion: %s). File was not published to centreon-vm.\n" \
            "${run_id}" "${transfer_ok}" >&2
        exit 1
    fi

    local log major os_found file md5 size
    log=$(gh run view "${run_id}" --repo "${PUBLISHER_REPO}" --log --job "${job_id}")

    major=$(grep -oE 'MAJOR_VERSION: [0-9]+\.[0-9]+' <<< "${log}" | tail -1 | cut -d' ' -f2)
    os_found=$(grep -oE 'OS_VERSION: [a-z0-9]+' <<< "${log}" | tail -1 | cut -d' ' -f2)
    file=$(grep -oE 'FILE: \S+' <<< "${log}" | tail -1 | cut -d' ' -f2)
    md5=$(grep -oE 'OVA MD5: [0-9a-f]+' <<< "${log}" | tail -1 | awk '{print $3}')
    size=$(grep -oE 'OVA SIZE: [0-9]+' <<< "${log}" | tail -1 | awk '{print $3}')

    if [[ -z "${major}" || -z "${os_found}" || -z "${file}" || -z "${md5}" || -z "${size}" ]]; then
        echo "Error: could not extract FILE/MD5/SIZE from run ${run_id} logs." >&2
        exit 1
    fi
    if [[ "${major}" != "${train}" ]]; then
        printf "Error: run %s is for train %s, not %s. Wrong run ID?\n" "${run_id}" "${major}" "${train}" >&2
        exit 1
    fi
    if [[ "${os_found}" != "${expected_os}" ]]; then
        printf "Error: run %s is for os %s, not %s. Wrong run ID?\n" "${run_id}" "${os_found}" "${expected_os}" >&2
        exit 1
    fi

    # Set directly in the caller's shell (no command substitution) so that a
    # failed "exit 1" above terminates the whole script instead of just a
    # subshell — extract_run must NOT be called as "$(extract_run ...)".
    EXTRACTED_MD5="${md5}"
    EXTRACTED_SIZE="${size}"
}

echo ""
echo "Reading run ${run_id_alma9} (alma9)..."
extract_run "${run_id_alma9}" "alma9"
md5_alma9="${EXTRACTED_MD5}"
size_alma9="${EXTRACTED_SIZE}"
printf "  ${GREEN}md5=%s size=%s${RESET}\n" "${md5_alma9}" "${size_alma9}"

echo "Reading run ${run_id_deb12} (deb12)..."
extract_run "${run_id_deb12}" "deb12"
md5_deb12="${EXTRACTED_MD5}"
size_deb12="${EXTRACTED_SIZE}"
printf "  ${GREEN}md5=%s size=%s${RESET}\n" "${md5_deb12}" "${size_deb12}"

# ── Step 3: Build the YAML entries ────────────────────────────────────────────
target_path="src/data/releases/${train}/${train}-${release_number}.yaml"
branch_name="release-ova-${train}-${release_number}"

build_entry() {
    local product="$1" os="$2" md5="$3" size="$4"
    cat <<EOF
- product: "${product}"
  train: "${train}"
  state: "stable"
  os: "${os}"
  version: "${train}-${release_number}.${os}"
  file: "${product}-${train}-${release_number}.${os}.zip"
  date: "${TODAY}"
  md5: "${md5}"
  size: ${size}
  enabled: true
EOF
}

yaml_content=$(
    build_entry "centreon-vbox-vm" "alma9" "${md5_alma9}" "${size_alma9}"
    build_entry "centreon-vbox-vm" "deb12" "${md5_deb12}" "${size_deb12}"
    build_entry "centreon-vmware-vm" "alma9" "${md5_alma9}" "${size_alma9}"
    build_entry "centreon-vmware-vm" "deb12" "${md5_deb12}" "${size_deb12}"
)

echo ""
printf "${BOLD}Generated %s:${RESET}\n" "${target_path}"
echo "${yaml_content}"

echo ""
read -rp "Proceed? (Y/n): " proceed_input
proceed_input="${proceed_input//$'\r'/}"
if [[ -n "${proceed_input}" && ! "${proceed_input}" =~ ^[Yy]$ ]]; then
    echo "Aborted."
    exit 0
fi

# ── Step 4: Determine target state ────────────────────────────────────────────
echo ""
echo "Checking existing state on ${CATALOG_REPO}..."

existing_pr_json=$(gh pr list --repo "${CATALOG_REPO}" --head "${branch_name}" --state open --json number,url --jq '.[0] // empty')
merged_sha=$(gh api "repos/${CATALOG_REPO}/contents/${target_path}?ref=${CATALOG_BASE_BRANCH}" --jq '.sha' 2>/dev/null || true)

if [[ -n "${existing_pr_json}" ]]; then
    pr_url=$(jq -r '.url' <<< "${existing_pr_json}")
    printf "\n${YELLOW}An open PR already exists for %s: %s${RESET}\n" "${branch_name}" "${pr_url}"
    echo "Not touching it — close it or update it manually if this is a rebuild, then re-run."
    exit 0
elif [[ -n "${merged_sha}" ]]; then
    mode="post-merge-fix"
    branch_name="${branch_name}-fix-$(date +%H%M)"
    base_sha=$(gh api "repos/${CATALOG_REPO}/git/refs/heads/${CATALOG_BASE_BRANCH}" --jq '.object.sha')
    commit_msg="fix: correct merged OVA ${train} build ${release_number} md5/size (rebuild)"
    printf "  ${YELLOW}%s is already merged on %s — opening a correction PR on %s.${RESET}\n" \
        "${target_path}" "${CATALOG_BASE_BRANCH}" "${branch_name}"
else
    mode="fresh"
    base_sha=$(gh api "repos/${CATALOG_REPO}/git/refs/heads/${CATALOG_BASE_BRANCH}" --jq '.object.sha')
    commit_msg="release: OVA ${train} build ${release_number} (vbox/vmware, alma9/deb12)"
    echo "  No existing branch/PR — creating fresh."
fi

base_tree=$(gh api "repos/${CATALOG_REPO}/git/commits/${base_sha}" --jq '.tree.sha')

# ── Step 5: Create the commit via the GitHub API ──────────────────────────────
echo ""
echo "Creating blob..."
printf '%s' "${yaml_content}" > "${CONTENTFILE}"
jq -n --rawfile content "${CONTENTFILE}" '{"encoding":"base64","content":($content | @base64)}' > "${TMPFILE}"
blob_sha=$(gh api "repos/${CATALOG_REPO}/git/blobs" --method POST --input "${TMPFILE}" --jq '.sha')

echo "Creating tree..."
jq -n --arg base "${base_tree}" --arg path "${target_path}" --arg sha "${blob_sha}" \
    '{"base_tree":$base,"tree":[{"path":$path,"mode":"100644","type":"blob","sha":$sha}]}' > "${TMPFILE}"
new_tree=$(gh api "repos/${CATALOG_REPO}/git/trees" --method POST --input "${TMPFILE}" --jq '.sha')

echo "Creating commit..."
jq -n --arg msg "${commit_msg}" --arg tree "${new_tree}" --arg parent "${base_sha}" \
    '{"message":$msg,"tree":$tree,"parents":[$parent]}' > "${TMPFILE}"
new_commit=$(gh api "repos/${CATALOG_REPO}/git/commits" --method POST --input "${TMPFILE}" --jq '.sha')

echo "Creating branch ${branch_name}..."
jq -n --arg ref "refs/heads/${branch_name}" --arg sha "${new_commit}" '{"ref":$ref,"sha":$sha}' > "${TMPFILE}"
gh api "repos/${CATALOG_REPO}/git/refs" --method POST --input "${TMPFILE}" > /dev/null

# ── Step 6: Open the PR ────────────────────────────────────────────────────────
echo ""
note=""
if [[ "${mode}" == "post-merge-fix" ]]; then
    note=$'\n**Note:** this corrects a build already merged on '"${CATALOG_BASE_BRANCH}"$' (rebuilt OVA).\n'
fi

pr_body="## Summary
- OVA appliance release for train ${train}, build ${release_number} (alma9 + deb12)
- 4 catalog entries: centreon-vbox-vm x {alma9,deb12} + centreon-vmware-vm x {alma9,deb12}
- Source runs (ova-publisher, ${PUBLISHER_REPO}):
  - alma9: https://github.com/${PUBLISHER_REPO}/actions/runs/${run_id_alma9}
  - deb12: https://github.com/${PUBLISHER_REPO}/actions/runs/${run_id_deb12}
- md5/size taken from the pipeline's own recomputed values (real md5 of the published zip, not the S3 ETag)
${note}
## Test plan
- [ ] \`pnpm validate\` passes in CI
- [ ] Preview build shows the build in the Appliances tab for train ${train}"

pr_url=$(gh pr create \
    --repo "${CATALOG_REPO}" \
    --base "${CATALOG_BASE_BRANCH}" \
    --head "${branch_name}" \
    --title "${commit_msg}" \
    --body "${pr_body}")

printf "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
printf "  PR: %s\n" "${pr_url}"
printf "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
