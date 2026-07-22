# Centreon TRAPD - Extracted Package Metadata

This document tracks the composition of extracted .deb packages for security audit and Scout scanning purposes.

## Extracted Components

### centreon-trap.deb
- **Binaries:** `centreontrapd`, `centreontrapdforward`
- **Location in image:** `/usr/share/centreon/bin/`
- **Version tracking:** Passed via `BUILD_VERSION` label at build time
- **Purpose:** Main SNMP trap handler and forwarding daemon

### centreon-perl-libs.deb
- **Modules:**
  - `centreon::script::centreontrapd` — main script module
  - `centreon::trapd::lib` — utility library
  - Other supporting modules from centreon-collect
- **Location in image:** `/usr/share/perl5/centreon/`
- **Selection logic:** `USE_SOURCE_TRAPD=false` (production) extracts from .deb; `USE_SOURCE_TRAPD=true` (dev) copies from source repo
- **Scout visibility:** Extracted via `dpkg-deb`, not tracked as apt packages; versions documented via labels

## Security Scanning Strategy

### Local Pre-Push Check
```bash
# Build locally with labels
docker build \
  --build-arg BUILD_DATE="$(date -u +'%Y-%m-%dT%H:%M:%SZ')" \
  --build-arg BUILD_VERSION="2.x.x-release" \
  --build-arg PACKAGE_SOURCES="centreon-trap-2.x.x + centreon-perl-libs-2.x.x" \
  -t centreon-centreontrapd:test \
  -f .github/docker/centreon-centreontrapd/trixie/Dockerfile .

# Run Scout CVE scan
docker scout cves centreon-centreontrapd:test --only-severities critical,high

# Generate SBOM
docker scout sbom centreon-centreontrapd:test --format cyclonedx > sbom-local.json
```

### CI Integration
- **SBOM formats generated:** CycloneDX (for tooling) + SPDX (for compliance)
- **Scan results:** Available in GitHub Actions workflow logs
- **CVE report:** Automatic via `docker/scout-action`, pinned to a SHA per repo convention
- **Blocking behavior:** currently non-blocking on all branches (informational only, PoC stage). A stable-only blocking gate is a possible follow-up but is not yet implemented — see "Known Limitations" below.

### Known Limitations

1. **Extracted Perl modules:** Not tracked as Debian apt packages by Scout
   - Workaround: Image labels document `.deb` package versions
   - Manual tracking: See `org.centreon.package-sources` label

2. **Binary dependencies:** If centreontrapd/centreontrapdforward link to system libraries, only Debian base packages are scanned
   - Mitigation: Regular apt package updates in Dockerfile
   - Check with: `ldd /usr/share/centreon/bin/centreontrapd`

3. **Transitive vulnerabilities in Perl:** Perl modules inside .deb won't show individual CVE details
   - Mitigation: Pin `.deb` package versions; track upstream centreon-collect releases
   - Monitor: centreon-collect repository for security advisories

4. **Stable-branch CVE gate not implemented yet:** `dockerize` (and therefore `docker-scout-cves`, which depends on it) is skipped on stable branches — stable releases are promoted to Pulp by retagging an already-scanned testing-branch image, not by rebuilding. A dedicated strict-check job for that path needs to reference the same `HARBOR_TAG` reconstruction logic used by `promote-docker-to-pulp`, not `dockerize.outputs.image_tag`.

## Build Metadata

### Image Labels

Automatically added during CI build (both `centreon-centreontrapd` and `centreon-snmptrapd` images):

```dockerfile
org.opencontainers.image.title: "Centreon TRAPD" # or "Centreon SNMPTRAPD"
org.opencontainers.image.description: "..."
org.opencontainers.image.created: "${BUILD_DATE}"         # e.g., 2026-07-22T10:30:00Z
org.opencontainers.image.version: "${BUILD_VERSION}"      # e.g., 24.10-1
org.opencontainers.image.vendor: "Centreon"
org.centreon.package-sources: "${PACKAGE_SOURCES}"        # e.g., "centreon-trap-24.10 + centreon-perl-libs-24.10"
org.centreon.base-image: "debian:13-slim"
```

### Inspect Labels

```bash
docker inspect --format='{{json .Config.Labels}}' <image> | jq
```

## SBOM Artifact Storage

Scout generates SBOMs in two formats, both uploaded as workflow artifacts with 30-day retention:

1. **CycloneDX** (`sbom-*-cyclonedx.json`) — best for automation/tooling
2. **SPDX** (`sbom-*-spdx.json`) — best for compliance/audit

Access from GitHub Actions → workflow run → Artifacts → `sbom-*`.

## Verification Checklist

Before promoting to Pulp (stable releases):

- [ ] **Scout CVE scan** completed (informational at this PoC stage)
- [ ] **SBOM artifacts** uploaded and reviewed (check component versions match expected)
- [ ] **Image labels** present and accurate (`docker inspect`) on both `centreon-centreontrapd` and `centreon-snmptrapd`
- [ ] **Boot tests** pass on both amd64 and arm64
- [ ] **Config/wiring tests** pass

## Next Steps

This is a proof-of-concept/proof-of-value pass (no stable-branch enforcement yet):

1. Evaluate Scout scan output and SBOM quality over a few CI runs
2. If useful, design the stable-only strict-check job correctly (see Known Limitations #4)
3. Only then consider making it blocking for stable releases
