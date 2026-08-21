#!/usr/bin/env bash
# Promote a Docker image already built and validated on Harbor to ghcr.io, our
# public delivery registry (MON-207531). Harbor stays the internal system of
# record for candidate + testing images; this script only runs once a stable
# release lands (stability == 'stable'), copying the already-built Harbor
# image straight to ghcr.io under each requested tag.
#
# Meant to be invoked from a workflow step with `continue-on-error: true`:
# this script fails fast internally (set -e) but the caller decides that a
# ghcr.io-side failure never blocks the stable release itself — Harbor
# delivery already happened independently. The caller is expected to surface
# a visible warning when this script fails, since a failure here means a
# stable release did not reach the public registry.
#
# The image is republished as a manifest list referencing only the
# per-platform manifests from Harbor, deliberately excluding the OCI
# attestation/referrers manifest (SLSA/in-toto provenance) that
# docker/build-push-action attaches by default (see MON-204486: some
# registries choke on committing a manifest that includes that attestation
# subject). Harbor keeps full provenance; only the ghcr.io copy omits it.
#
# Expected env vars:
#   GHCR_IMAGE   destination image ref on ghcr.io, no tag (e.g. ghcr.io/centreon/centreon-snmptrapd)
#   HARBOR_IMAGE source image ref on Harbor, no tag
#   HARBOR_TAG   source tag on Harbor to promote (e.g. release-26.10-next)
#   GHCR_TAGS    space-separated list of tags to create on ghcr.io (e.g. "26.10.5 26.10")
#   PLATFORMS    comma-separated platform list, informational only (e.g. linux/amd64,linux/arm64)
set -euo pipefail

if [[ -z "${GHCR_TAGS// /}" ]]; then
  echo "::error::GHCR_TAGS is empty — nothing would be published to $GHCR_IMAGE. Refusing to report success."
  exit 1
fi

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

echo "::group::Publish ghcr.io stable tags (manifest list, no rebuild, no attestation)"
for tag in $GHCR_TAGS; do
  echo "Publishing $GHCR_IMAGE:$tag from $SRC_REF (${#SRC_REFS[@]} platform manifest(s))"
  retry_imagetools_create "$GHCR_IMAGE:$tag" "${SRC_REFS[@]}"
done
echo "::endgroup::"

echo "ghcr.io stable promote complete: $GHCR_IMAGE ($GHCR_TAGS)"
