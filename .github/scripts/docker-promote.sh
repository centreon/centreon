#!/usr/bin/env bash
# Copy a validated Harbor candidate to one delivery registry (ghcr.io, Pulp),
# byte for byte, under each requested tag (MON-212204). crane copy keeps the
# index digest identical to the Harbor build, so the build provenance and SBOM
# attestations (GitHub attestation API, keyed by digest) verify on every
# destination without being regenerated per registry.
#
# Meant to be invoked from a step with `continue-on-error: true`: one
# destination failing never blocks the others nor the stable release.
#
# Registry auth is set up by the caller, except for Pulp: when
# PULP_OIDC_AUDIENCE is set, a fresh GitHub OIDC token is stored as the
# "__token__" basic credential before each crane call, Pulp's token service
# exchanging it for a registry token (workload identity).
#
# Expected env vars:
#   SOURCE_REF          source image pinned by digest (<image>@sha256:...)
#   DEST_IMAGE          destination image ref, no tag
#   DEST_TAGS           space-separated list of tags to create
#   PULP_OIDC_AUDIENCE  optional, Pulp destination only
#
# Outputs ($GITHUB_OUTPUT):
#   digest              promoted index digest (== source digest)
set -euo pipefail

if [[ -z "${DEST_TAGS// /}" ]]; then
  echo "::error::DEST_TAGS is empty — nothing would be published to $DEST_IMAGE."
  exit 1
fi

EXPECTED_DIGEST="${SOURCE_REF##*@}"
if [[ ! "$EXPECTED_DIGEST" =~ ^sha256:[0-9a-f]{64}$ ]]; then
  echo "::error::SOURCE_REF must be pinned by digest, got '$SOURCE_REF'"
  exit 1
fi

DEST_REGISTRY="${DEST_IMAGE%%/*}"

refresh_auth() {
  [[ -z "${PULP_OIDC_AUDIENCE:-}" ]] && return 0
  if [[ -z "${ACTIONS_ID_TOKEN_REQUEST_URL:-}" || -z "${ACTIONS_ID_TOKEN_REQUEST_TOKEN:-}" ]]; then
    echo "::error::Missing OIDC token request context, the job needs 'permissions: id-token: write'"
    return 1
  fi
  local oidc_token
  oidc_token=$(
    curl -fsSL -H "Authorization: bearer $ACTIONS_ID_TOKEN_REQUEST_TOKEN" \
      -G --data-urlencode "audience=$PULP_OIDC_AUDIENCE" \
      "$ACTIONS_ID_TOKEN_REQUEST_URL" | jq -r '.value'
  )
  if [[ -z "$oidc_token" || "$oidc_token" == "null" ]]; then
    echo "::error::Could not obtain a GitHub OIDC token for $DEST_REGISTRY"
    return 1
  fi
  echo "::add-mask::$oidc_token"
  echo "$oidc_token" | crane auth login "$DEST_REGISTRY" --username __token__ --password-stdin
}

if [[ -n "${PULP_OIDC_AUDIENCE:-}" ]]; then
  # don't leave the credential on persistent self-hosted runners
  trap 'crane auth logout "$DEST_REGISTRY" >/dev/null 2>&1 || true' EXIT
fi

copy_with_retry() {
  local dest="$1"
  local attempt max_attempts=5 delay=5
  for attempt in $(seq 1 "$max_attempts"); do
    if refresh_auth && crane copy "$SOURCE_REF" "$dest"; then
      return 0
    fi
    if [[ "$attempt" -eq "$max_attempts" ]]; then
      break
    fi
    echo "crane copy failed (attempt $attempt/$max_attempts), retrying in ${delay}s..."
    sleep "$delay"
    delay=$((delay * 2))
  done
  return 1
}

for tag in $DEST_TAGS; do
  echo "::group::Publish $DEST_IMAGE:$tag"
  copy_with_retry "$DEST_IMAGE:$tag"
  refresh_auth
  actual_digest=$(crane digest "$DEST_IMAGE:$tag")
  if [[ "$actual_digest" != "$EXPECTED_DIGEST" ]]; then
    echo "::error::$DEST_IMAGE:$tag resolves to $actual_digest instead of $EXPECTED_DIGEST: the registry rewrote the index, attestations won't match it."
    exit 1
  fi
  echo "$DEST_IMAGE:$tag -> $actual_digest"
  echo "::endgroup::"
done

echo "digest=$EXPECTED_DIGEST" >> "$GITHUB_OUTPUT"
echo "Promote complete: $DEST_IMAGE ($DEST_TAGS) @ $EXPECTED_DIGEST"
