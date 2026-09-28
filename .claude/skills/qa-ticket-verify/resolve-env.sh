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

head_ref=$(gh pr view "$pr_number" --repo "$repo" --json headRefName --jq .headRefName)

# Same sanitization as the "Compute image tag" step of web.yml, which tags the image.
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

cat <<EOF
MODULE=centreon-web
OTHER_MODULES=
WEB_IMAGE=$registry/centreon-web-slim-$os:$tag
MBI_IMAGE=
COMPOSE_PROFILE=
E2E_DIR=centreon/tests/e2e/features
FRONT_DIRS=centreon/www/front_src/src centreon/www/include
EOF
