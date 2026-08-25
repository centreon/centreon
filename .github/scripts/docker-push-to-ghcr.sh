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
# MON-207622: that Harbor attestation manifest is BuildKit's default,
# unsigned in-toto provenance — it has no Sigstore/Fulcio/Rekor trust chain,
# so `gh attestation verify`/`cosign verify-attestation` (which only validate
# GitHub-native, Sigstore-signed attestations) could never verify it even if
# it were copied across registries. Decision: leave the exclusion above
# untouched, and instead generate fresh, GitHub-native provenance + SBOM
# attestations against the ghcr.io digest after promotion (see the caller
# composite action `attest-ghcr-image`). To make that possible, this script
# also resolves and emits the promoted manifest-list digest (and the amd64
# platform digest, since an SBOM must target a single-platform manifest, not
# a multi-arch index) as step outputs — resolved from the ghcr.io side after
# publish, not assumed from Harbor, since imagetools create's cross-registry
# digest preservation is a property to verify, not to bet delivery on.
# Resolution failures here are non-fatal (a warning, no outputs): they would
# only skip attestation, never turn a successful publish into a reported
# failure — that distinction is what the caller's attest-ghcr-image step and
# its separate warning message rely on.
#
# Expected env vars:
#   GHCR_IMAGE   destination image ref on ghcr.io, no tag (e.g. ghcr.io/centreon/centreon-snmptrapd)
#   HARBOR_IMAGE source image ref on Harbor, no tag
#   HARBOR_TAG   source tag on Harbor to promote (e.g. release-26.10-next)
#   GHCR_TAGS    space-separated list of tags to create on ghcr.io (e.g. "26.10.5 26.10")
#   PLATFORMS    comma-separated platform list, informational only (e.g. linux/amd64,linux/arm64)
#
# Outputs (written to $GITHUB_OUTPUT, best-effort — may be absent):
#   digest       digest of the promoted ghcr.io manifest list (identical across all GHCR_TAGS)
#   amd64_digest digest of the amd64 platform manifest, for SBOM scanning
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

echo "::group::Resolve promoted digest (for attestation, best-effort)"
GHCR_DIGEST=""
AMD64_DIGEST=""
DIGEST_RESOLUTION_OK=true
for tag in $GHCR_TAGS; do
  [[ -z "$tag" ]] && continue
  if ! DEST_MANIFEST=$(docker buildx imagetools inspect "$GHCR_IMAGE:$tag" --format '{{json .Manifest}}'); then
    echo "::warning::Could not inspect $GHCR_IMAGE:$tag after publish — skipping attestation for this promote run (image is still published)"
    DIGEST_RESOLUTION_OK=false
    break
  fi
  tag_digest=$(echo "$DEST_MANIFEST" | jq -r '.digest')
  if [[ -z "$GHCR_DIGEST" ]]; then
    GHCR_DIGEST="$tag_digest"
    AMD64_DIGEST=$(echo "$DEST_MANIFEST" | jq -r '.manifests[]? | select(.platform.architecture == "amd64") | .digest' | head -n1)
  elif [[ "$tag_digest" != "$GHCR_DIGEST" ]]; then
    echo "::warning::Published tags resolved to different digests ($GHCR_DIGEST vs $tag_digest for tag $tag) — skipping attestation for this promote run (ambiguous subject, image is still published)"
    DIGEST_RESOLUTION_OK=false
    break
  fi
done
if [[ "$DIGEST_RESOLUTION_OK" == true && "$GHCR_DIGEST" =~ ^sha256:[0-9a-f]{64}$ && -n "$AMD64_DIGEST" ]]; then
  echo "Resolved digest: $GHCR_DIGEST (amd64 platform digest for SBOM: $AMD64_DIGEST)"
  echo "digest=$GHCR_DIGEST" >> "$GITHUB_OUTPUT"
  echo "amd64_digest=$AMD64_DIGEST" >> "$GITHUB_OUTPUT"
else
  echo "::warning::Could not resolve a usable digest/amd64_digest for $GHCR_IMAGE — attestation step will be skipped for this promote run (image is still published)"
fi
echo "::endgroup::"

echo "ghcr.io stable promote complete: $GHCR_IMAGE ($GHCR_TAGS)"
