#!/usr/bin/env bash
set -euo pipefail

# shellcheck source=.github/scripts/pulp/api.sh
source "$(dirname "$0")/../../scripts/pulp/api.sh"

# an unset org variable is forwarded as an empty string, overriding the default
PULP_URL="${PULP_URL:-https://pulp-api.int.centreon.com}"
PULP_DOMAIN="${PULP_DOMAIN:-default}"

# feature repositories are provisioned on demand by delivery-tooling create-repos
# (repository_dispatch), which creates every distribution of the major version in
# both the standard and business domains: wait for the ones this delivery targets
MAX_ATTEMPTS=40
POLL_INTERVAL=15

if [[ -z "${CENTREON_TECHNIQUE_PAT:-}" ]]; then
  echo "::error::centreon_technique_pat input is required to request the feature repository creation"
  exit 1
fi

if [[ -z "${VERSION:-}" ]]; then
  echo "::error::version input is required for a feature delivery"
  exit 1
fi

FEATURE_TICKET=$(echo "$GH_HEAD_REF" | grep -oE 'MON-[0-9]+' | head -1 || true)
if [[ -z "$FEATURE_TICKET" ]]; then
  echo "::error::Cannot extract the feature ticket from branch name $GH_HEAD_REF"
  exit 1
fi

# the delivery checks both the repository and the distribution; create-repos
# creates the distribution last (after the initial publication), so its presence
# means the repository is ready to receive packages
feature_repositories_exist() {
  local arch
  for arch in noarch x86_64; do
    pulp_resource_exists "repositories/rpm/rpm" "$REPOSITORY_PREFIX-$arch" || return 1
    pulp_resource_exists "distributions/rpm/rpm" "$REPOSITORY_PREFIX-$arch" || return 1
  done
}

if feature_repositories_exist; then
  echo "[INFO] Feature repositories $REPOSITORY_PREFIX-{noarch,x86_64} already exist in domain $PULP_DOMAIN"
  exit 0
fi

echo "[INFO] Requesting the creation of the $FEATURE_TICKET feature repositories for $VERSION"
curl --fail-with-body -sS -X POST \
  -H "Authorization: Bearer $CENTREON_TECHNIQUE_PAT" \
  -H "Accept: application/vnd.github.v3+json" \
  https://api.github.com/repos/centreon/delivery-tooling/dispatches \
  -d "$(jq -n \
    --arg major_version "$VERSION" \
    --arg repo_type "feature" \
    --arg feature_ticket "$FEATURE_TICKET" \
    --arg target "pulp" \
    '{
      event_type: "create-feature-repositories",
      client_payload: {
        major_version: $major_version,
        repo_type: $repo_type,
        feature_ticket: $feature_ticket,
        target: $target
      }
    }'
  )"

for ((attempt = 1; attempt <= MAX_ATTEMPTS; attempt++)); do
  sleep "$POLL_INTERVAL"
  echo "[INFO] Checking if the feature repositories have been created... [ATTEMPT $attempt/$MAX_ATTEMPTS]"
  if feature_repositories_exist; then
    echo "[INFO] Feature repositories $REPOSITORY_PREFIX-{noarch,x86_64} are ready in domain $PULP_DOMAIN"
    exit 0
  fi
done

echo "::error::Timeout waiting for the feature repositories creation. Aborting delivery process. Please contact someone from the DevSecOps team."
exit 1
