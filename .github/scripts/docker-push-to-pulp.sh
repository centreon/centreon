#!/usr/bin/env bash
# Promote a Docker image already built and validated on Harbor to Pulp, our
# public delivery registry (MON-204486/MON-201979). Harbor stays the internal
# system of record for candidate + testing images; this script only runs once
# a stable release lands (stability == 'stable'), copying the already-built
# Harbor image straight to Pulp with the release version as tag.
#
# Meant to be invoked from a workflow step with `continue-on-error: true`:
# this script fails fast internally (set -e) but the caller decides that a
# Pulp-side failure never blocks the stable release itself — Harbor delivery
# already happened independently. The caller is expected to surface a
# visible warning when this script fails, since a failure here means a
# stable release did not reach the public registry.
#
# The image is rebuilt as a manifest list referencing only the per-platform
# manifests from Harbor, deliberately excluding the OCI attestation/referrers
# manifest (SLSA/in-toto provenance) that docker/build-push-action attaches
# by default: Pulp's container registry returns a deterministic 500 when
# committing a manifest that includes that attestation subject (confirmed via
# a provenance:false before/after test in centreon-collect). Harbor keeps
# full provenance; only the Pulp copy omits it.
#
# Expected env vars:
#   PULP_URL             e.g. https://pulp-api.apps.centreon.com
#   PULP_OIDC_AUDIENCE   e.g. https://pulp.dev.centreon.io
#   BASE_PATH            Pulp container path, e.g. centreon/centreon-centreontrapd-trixie
#   HARBOR_IMAGE         source image ref on Harbor, no tag
#   HARBOR_TAG           source tag on Harbor to promote (e.g. major_version "24.10")
#   PULP_TAGS            space-separated list of tags to create on Pulp (e.g. "24.10.5 24.10")
#   MODULE, OS, STABILITY, PLATFORMS
#   ACTIONS_ID_TOKEN_REQUEST_TOKEN / ACTIONS_ID_TOKEN_REQUEST_URL (injected by GitHub Actions when the job has `id-token: write`)
set -euo pipefail

PULP_IMAGE="pulp-api.apps.centreon.com/$BASE_PATH"

echo "::group::Fetch GitHub OIDC token for Pulp"
OIDC_TOKEN=$(curl -sS \
  -H "Authorization: bearer $ACTIONS_ID_TOKEN_REQUEST_TOKEN" \
  "$ACTIONS_ID_TOKEN_REQUEST_URL&audience=$PULP_OIDC_AUDIENCE" \
  | jq -r '.value')
if [[ -z "$OIDC_TOKEN" || "$OIDC_TOKEN" == "null" ]]; then
  echo "::error::Failed to obtain a GitHub OIDC token for Pulp"
  exit 1
fi
echo "::add-mask::$OIDC_TOKEN"
echo "::endgroup::"

echo "::group::Ensure Pulp container distribution exists"
EXISTING=$(curl -sS \
  -H "Authorization: Bearer $OIDC_TOKEN" \
  -G --data-urlencode "base_path=$BASE_PATH" \
  "$PULP_URL/api/v3/distributions/container/container/" \
  | jq -r '.results[0].pulp_href // empty')
if [[ -z "$EXISTING" ]]; then
  echo "Distribution $BASE_PATH not found, creating..."
  TASK_HREF=$(curl -sS -f -X POST \
    -H "Authorization: Bearer $OIDC_TOKEN" \
    -H "Content-Type: application/json" \
    -d "{\"name\":\"$BASE_PATH\",\"base_path\":\"$BASE_PATH\"}" \
    "$PULP_URL/api/v3/distributions/container/container/" \
    | jq -r '.task')
  echo "Waiting for creation task $TASK_HREF to complete..."
  TASK_STATE=""
  for _ in $(seq 1 30); do
    TASK_STATE=$(curl -sS -H "Authorization: Bearer $OIDC_TOKEN" "$PULP_URL$TASK_HREF" | jq -r '.state')
    echo "  task state: $TASK_STATE"
    if [[ "$TASK_STATE" == "completed" ]]; then
      break
    elif [[ "$TASK_STATE" == "failed" || "$TASK_STATE" == "canceled" ]]; then
      echo "::error::Pulp distribution creation task ended in state '$TASK_STATE'"
      exit 1
    fi
    sleep 2
  done
  if [[ "$TASK_STATE" != "completed" ]]; then
    echo "::error::Timed out waiting for Pulp distribution creation task to complete"
    exit 1
  fi
else
  echo "Distribution $BASE_PATH exists: $EXISTING"
fi
echo "::endgroup::"

echo "::group::Login to Pulp registry via OIDC"
PULP_JWT=$(curl -sS \
  -H "Authorization: Bearer $OIDC_TOKEN" \
  "$PULP_URL/token/?service=pulp-api.apps.centreon.com&scope=repository:${BASE_PATH}:push,pull" \
  | jq -r '.token // empty')
if [[ -z "$PULP_JWT" ]]; then
  echo "::error::Failed to fetch Pulp JWT (token service returned empty)"
  exit 1
fi
echo "::add-mask::$PULP_JWT"
mkdir -p ~/.docker
EXISTING_CONFIG=$(cat ~/.docker/config.json 2>/dev/null || echo '{}')
echo "$EXISTING_CONFIG" | jq --arg jwt "$PULP_JWT" \
  '.auths["pulp-api.apps.centreon.com"] = {"registrytoken": $jwt}' \
  > ~/.docker/config.json
echo "::endgroup::"

echo "::group::Resolve Harbor per-platform manifests (excluding attestation/provenance)"
SRC_REF="$HARBOR_IMAGE:$HARBOR_TAG"
RAW_MANIFEST=$(docker buildx imagetools inspect "$SRC_REF" --raw)
mapfile -t PLATFORM_DIGESTS < <(echo "$RAW_MANIFEST" | jq -r '.manifests[] | select(.platform.architecture != "unknown") | .digest')
if [[ ${#PLATFORM_DIGESTS[@]} -eq 0 ]]; then
  echo "::error::No platform manifests found for $SRC_REF"
  exit 1
fi
SRC_REFS=()
for digest in "${PLATFORM_DIGESTS[@]}"; do
  SRC_REFS+=("$HARBOR_IMAGE@$digest")
  echo "  platform manifest: $digest"
done
echo "::endgroup::"

retry_imagetools_create() {
  local dest="$1"; shift
  local attempt max_attempts=5 delay=5
  for attempt in $(seq 1 "$max_attempts"); do
    if docker buildx imagetools create --tag "$dest" "$@"; then
      return 0
    fi
    if [[ "$attempt" -eq "$max_attempts" ]]; then
      break
    fi
    echo "imagetools create failed (attempt $attempt/$max_attempts), retrying in ${delay}s..."
    sleep "$delay"
    delay=$((delay * 2))
  done
  return 1
}

echo "::group::Publish Pulp stable tags (manifest list, no rebuild, no attestation)"
for tag in $PULP_TAGS; do
  echo "Publishing $PULP_IMAGE:$tag from $SRC_REF (${#SRC_REFS[@]} platform manifest(s))"
  retry_imagetools_create "$PULP_IMAGE:$tag" "${SRC_REFS[@]}"
done
echo "::endgroup::"

echo "::group::Set Pulp labels on container distribution"
ARCHITECTURES=$(echo "$PLATFORMS" | tr ',' '\n' | sed 's|linux/||' | tr '\n' ' ' | sed 's/ $//')
HREF=$(curl -fsSL -H "Authorization: Bearer $OIDC_TOKEN" \
  -G --data-urlencode "base_path=$BASE_PATH" \
  "$PULP_URL/api/v3/distributions/container/container/" \
  | jq -r '.results[0].pulp_href')
if [[ -z "$HREF" || "$HREF" == "null" ]]; then
  echo "::error::Distribution not found for base_path=$BASE_PATH"
  exit 1
fi
RESPONSE=$(curl -sS -w "\nHTTP_STATUS:%{http_code}" -X PATCH -H "Authorization: Bearer $OIDC_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"pulp_labels\": {\"module\": \"$MODULE\", \"os\": \"$OS\", \"stability\": \"$STABILITY\", \"tags\": \"$PULP_TAGS\", \"architectures\": \"$ARCHITECTURES\"}}" \
  "$PULP_URL$HREF")
HTTP_STATUS=$(echo "$RESPONSE" | grep -o "HTTP_STATUS:[0-9]*" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_STATUS:/d')
echo "Pulp response ($HTTP_STATUS): $BODY"
if [[ "$HTTP_STATUS" != "200" && "$HTTP_STATUS" != "202" ]]; then
  echo "::error::Failed to set Pulp labels (HTTP $HTTP_STATUS)"
  exit 1
fi
echo "::endgroup::"

echo "Pulp stable promote complete: $PULP_IMAGE ($PULP_TAGS)"
