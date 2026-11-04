#!/usr/bin/env bash
# Resolve the QA environment of a centreon/centreon PR for the qa-ticket-verify skill.
#
# Usage: resolve-env.sh <pr-number>
#
# Prints KEY=value lines, ready for "$GITHUB_ENV":
#   MODULE, OTHER_MODULES, WEB_IMAGE, MBI_IMAGE, COMPOSE_PROFILE, E2E_DIR, FRONT_DIRS
# centreon/centreon-modules ships its own resolve-env.sh printing the same keys.

set -euo pipefail

pr_number=${1:?usage: resolve-env.sh <pr-number>}
repo=${GITHUB_REPOSITORY:-centreon/centreon}
registry=docker.centreon.com/centreon

# Some top-level directories bundle their own Centreon module and build their own dedicated
# image (centreon-web + the module's RPM, see .github/docker/<dir>/<os>/Dockerfile and
# .github/workflows/<dir>.yml's own "dockerize" job) instead of being part of plain
# centreon-web-slim. Testing a PR that touches one of these against centreon-web-slim would
# make every scenario involving it unreachable (the module isn't installed at all). Add an
# entry here whenever a new bundled module gets its own .github/docker/<dir> image.
declare -A bundled_images=(
  [centreon-open-tickets]=centreon-open-tickets
  [centreon-awie]=centreon-awie
)
declare -A bundled_e2e=(
  [centreon-open-tickets]=centreon-open-tickets/tests/e2e/features
  [centreon-awie]=centreon-awie/tests/e2e/features
)
declare -A bundled_fronts=(
  [centreon-open-tickets]=centreon-open-tickets/www/modules/centreon-open-tickets
  [centreon-awie]=centreon-awie/www/modules/centreon-awie
)

pr=$(gh pr view "$pr_number" --repo "$repo" --json headRefName,files)
head_ref=$(jq -r .headRefName <<<"$pr")

# Same sanitization as the "Compute image tag" step of web.yml (and of every bundled module's
# own dockerize job, which tags its image the exact same way from the same branch name).
tag=$(printf '%s' "$head_ref" | sed -E 's#[^[:alnum:]_.-]+#-#g; s#^[.-]+##; s#-+#-#g' | cut -c1-128)
if [[ -z "$tag" ]]; then
  echo "::error::Unable to derive a Docker tag from branch '$head_ref'" >&2
  exit 1
fi

# Don't hardcode the OS variant: read what this PR's own CI built.
os=$(gh pr checks "$pr_number" --repo "$repo" --json name --jq '.[].name' \
  | grep -oE 'alma[0-9]+' | sort | uniq -c | sort -rn | head -1 | grep -oE 'alma[0-9]+' || true)
if [[ -z "$os" ]]; then
  echo "::warning::Could not detect the OS variant from PR #$pr_number's checks, defaulting to alma9" >&2
  os=alma9
fi

image_name=centreon-web-slim
e2e_dir=centreon/tests/e2e/features
front_dirs="centreon/www/front_src/src centreon/www/include"
other_modules=

touched_bundled=$(jq -r '.files[].path | split("/")[0]' <<<"$pr" | sort | uniq -c | sort -rn \
  | awk '{print $2}' | while read -r dir; do [[ -n "${bundled_images[$dir]:-}" ]] && echo "$dir"; done || true)

if [[ -n "$touched_bundled" ]]; then
  picked=$(head -1 <<<"$touched_bundled")
  image_name=${bundled_images[$picked]}
  # Both source trees stay in scope: the bundled image is centreon-web plus this module, so a
  # ticket can legitimately exercise either side.
  e2e_dir="centreon/tests/e2e/features ${bundled_e2e[$picked]}"
  front_dirs="$front_dirs ${bundled_fronts[$picked]}"
  other_modules=$(tail -n +2 <<<"$touched_bundled" | paste -sd' ' || true)
  if [[ -n "$other_modules" ]]; then
    echo "::warning::PR #$pr_number also touches $other_modules — only $picked's image ($image_name) is started this run, those scenarios aren't reachable" >&2
  fi
fi

cat <<EOF
MODULE=centreon-web
OTHER_MODULES=$other_modules
WEB_IMAGE=$registry/$image_name-$os:$tag
MBI_IMAGE=
COMPOSE_PROFILE=
E2E_DIR=$e2e_dir
FRONT_DIRS=$front_dirs
EOF
