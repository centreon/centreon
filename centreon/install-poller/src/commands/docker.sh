# Copyright 2025 - present Centreon Team
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
#     http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.

function _checkDockerPrerequisites() {
  local ret=0

  if ! type docker >/dev/null 2>&1; then
    consoleError "docker is not installed or not in PATH."
    logError "docker is not installed or not in PATH."
    ret=1
  fi

  if ! docker compose version >/dev/null 2>&1; then
    consoleError "docker compose (v2 plugin) is not available. Run 'docker compose version' to verify."
    logError "docker compose plugin is not available."
    ret=1
  fi

  return ${ret}
}

# centreon-vmware's Dockerfile lives in centreon-plugins (a different repo),
# and its image is never published (licensed Broadcom VMware Perl SDK), so it
# must be built locally. Validate the checkout + SDK archives are in place
# before we ever reference them as a compose build context, and fail with the
# exact command needed to fix it instead of letting `docker compose up` fail
# deep inside the build.
function _checkVmwarePrerequisites() {
  local plugins_path="${VMWARE_PATH:-./centreon-plugins}"
  local ret=0

  if [ ! -d "${plugins_path}" ] || [ ! -f "${plugins_path}/.github/docker/connector/Dockerfile.connector-vmware" ]; then
    consoleError "centreon-plugins checkout not found at '${plugins_path}'."
    consoleError "Clone it with: git clone https://github.com/centreon/centreon-plugins.git ${plugins_path}"
    consoleError "Or point to an existing checkout with --vmware-path <path>."
    logError "centreon-plugins checkout not found at '${plugins_path}'."
    ret=1
  else
    local missing_sdk=""
    [ -f "${plugins_path}/sdks-vmware/VMware-vSphere-Perl-SDK-7.0.0-17698549.x86_64.tar.gz" ] || missing_sdk="${missing_sdk}VMware-vSphere-Perl-SDK-7.0.0-17698549.x86_64.tar.gz "
    [ -f "${plugins_path}/sdks-vmware/vsan-sdk-perl.zip" ] || missing_sdk="${missing_sdk}vsan-sdk-perl.zip "

    if [ -n "${missing_sdk}" ]; then
      consoleError "Missing VMware SDK file(s) in '${plugins_path}/sdks-vmware/': ${missing_sdk% }"
      consoleError "Download them from the Broadcom Developer Portal and place them there — see ${plugins_path}/sdks-vmware/README.md."
      logError "Missing VMware SDK file(s) in '${plugins_path}/sdks-vmware/': ${missing_sdk% }"
      ret=1
    fi
  fi

  return ${ret}
}

# Decides once for both files (docker-compose.yaml references .env's vars,
# so a mixed in-place/.new outcome would be worse than either uniform one).
# Prompts on /dev/tty, not stdin (curl | bash consumes stdin for the script
# itself); no usable tty defaults to .new, never a silent overwrite.
function _resolveOverwriteSuffix() {
  local dir=$1

  DOCKER_FILE_SUFFIX=""

  if [ ! -f "${dir}/docker-compose.yaml" ] && [ ! -f "${dir}/.env" ]; then
    return
  fi

  if [ "${OVERWRITE}" = "1" ]; then
    consoleInfo "--overwrite given: regenerating existing docker-compose.yaml/.env in place."
    logInfo "Overwriting existing docker-compose.yaml/.env (--overwrite)."
    return
  fi

  consoleWarn "docker-compose.yaml and/or .env already exist in this directory."

  local reply=""
  # Scoped 2>/dev/null: a bare `exec 3<>/dev/tty 2>/dev/null` still leaks the
  # ENXIO error, since redirections apply left to right.
  if { exec 3<>/dev/tty; } 2>/dev/null; then
    printf "Overwrite them? [y/N] " >&3
    read -r reply <&3
    exec 3<&-
  else
    consoleWarn "No terminal available to prompt (non-interactive run)."
    consoleWarn "Writing new content to docker-compose.yaml.new/.env.new instead. Pass --overwrite to regenerate in place."
    logInfo "Non-interactive run with existing docker-compose.yaml/.env: defaulting to .new files."
    DOCKER_FILE_SUFFIX=".new"
    return
  fi

  case "${reply}" in
  y | Y | yes | YES)
    logInfo "User confirmed overwrite of existing docker-compose.yaml/.env."
    ;;
  *)
    consoleInfo "Keeping existing files. Writing new content to docker-compose.yaml.new/.env.new instead."
    logInfo "User declined overwrite; writing docker-compose.yaml.new/.env.new instead."
    DOCKER_FILE_SUFFIX=".new"
    ;;
  esac
}

function runDockerInstall() {
  echo ""
  consoleMainTitle "Generating Docker Compose files for Centreon poller"

  consoleTitle "Checking prerequisites:"
  _checkDockerPrerequisites || exit 1
  consoleInfo "docker and docker compose are available"
  if [ "${WITH_VMWARE}" = "1" ]; then
    _checkVmwarePrerequisites || exit 1
    consoleInfo "centreon-plugins checkout and VMware SDK found"
  fi
  echo ""

  _resolveOverwriteSuffix "."
  local suffix="${DOCKER_FILE_SUFFIX}"
  if [ "${suffix}" = ".new" ]; then
    if [ -f "./docker-compose.yaml.new" ] || [ -f "./.env.new" ]; then
      consoleError "docker-compose.yaml.new/.env.new already exist from a previous run. Review/merge or remove them before re-running."
      logError "Refusing to overwrite existing docker-compose.yaml.new/.env.new."
      exit 1
    fi
    # Old files are untouched; starting the stack would run stale ones.
    START_STACK=0
  fi

  _generateDotEnv "." "${suffix}"
  _generateDockerCompose "." "${suffix}"

  echo ""
  consoleTitle "Files generated:"
  consoleInfo "  docker-compose.yaml${suffix}"
  consoleInfo "  .env${suffix}"
  echo ""
  consoleTitle "Services included:"
  consoleInfo "  centengine, gorgone (always)"
  if [ "${WITH_VMWARE}" = "1" ]; then
    consoleInfo "  centreon-vmware (--with-vmware)"
  fi
  if [ "${WITH_SNMPTRAP}" = "1" ]; then
    consoleInfo "  snmptrapd, centreontrapd (--with-snmptrap)"
  fi
  if [ "${WITH_CMA}" = "1" ]; then
    consoleInfo "  Centreon Monitoring Agent support (--with-cma): TLS certs + port 4317"
  fi
  echo ""

  if [ "${suffix}" = ".new" ]; then
    consoleTitle "Next steps:"
    echo "  Existing docker-compose.yaml/.env were left untouched. Review and merge manually, e.g.:"
    echo "       diff docker-compose.yaml docker-compose.yaml.new"
    echo "       diff .env .env.new"
    echo ""
    return
  fi

  # No stability-based auto-start block: every case (stable/unstable/testing*)
  # now resolves TAG to a real, pullable tag (MON-208554 dropped the old
  # SET_ME_PER_COMPONENT placeholder), so --no-start/START_STACK alone decides.
  if [ "${START_STACK}" = "1" ]; then
    echo ""
    consoleTitle "Starting stack:"
    docker compose up -d || { consoleError "Failed to start stack."; logError "docker compose up -d failed (exit $?)"; exit 1; }
    echo ""
  else
    consoleTitle "Next steps:"
    echo "  1. Start the stack:"
    echo "       docker compose up -d"
    echo "  (optional) Copy TLS certificates for Centreon Monitoring Agent:"
    echo "       mkdir -p certs && cp poller.crt certs/ && cp poller.key certs/"
    echo ""
  fi
}

# Effective stability for every Docker decision (tag, registry, whether to
# auto-start the stack): FORCE_REGISTRY (hidden --registry flag), when set,
# pins it — 'ghcr' behaves like stable, 'harbor' behaves like testing —
# regardless of FORCE_STABILITY/STABILITY. This lets --registry harbor work
# on a build baked STABILITY=stable without also needing --stability testing.
# Otherwise falls back to FORCE_STABILITY (hidden --stability flag), then the
# real baked STABILITY. MON-208554.
function _dockerEffectiveStability() {
  case "${FORCE_REGISTRY}" in
  ghcr)
    echo "stable"
    return
    ;;
  harbor)
    echo "testing"
    return
    ;;
  esac
  echo "${FORCE_STABILITY:-${STABILITY}}"
}

function _generateDotEnv() {
  local dir=$1
  local suffix=${2:-}
  local out_file="${dir}/.env${suffix}"
  local tag_stability
  tag_stability="$(_dockerEffectiveStability)"
  logInfo "Generating ${out_file} (stability: ${tag_stability})"

  # Registry/repo selection (ghcr.io for stable, Harbor otherwise) happens in
  # _pollerImageRepo, used by _generateDockerCompose. Only the tag is decided
  # here (MON-207531 verified these tag conventions end-to-end).
  local image_tag
  if [ -n "${FORCE_TAG}" ]; then
    # Hidden --tag override: use verbatim, regardless of everything else.
    image_tag="${FORCE_TAG}"
  else
    case "${tag_stability}" in
    stable)
      image_tag="$(_pollerImageMajor)"
      ;;
    unstable)
      image_tag="develop"
      ;;
    testing | testing-release)
      # 'testing' here is the real baked STABILITY, unforced (no --stability
      # given): centreon-collect's testing channel is actually two repos/
      # tags (release vs hotfix candidates), so this guesses the more common
      # release one. Force --stability testing-hotfix for the other, or pass
      # the exact validated <major>.<minor>.<patch> semver retag via --tag
      # once known (after promote-docker-tag has run for that patch).
      image_tag="release-$(_pollerImageMajor)-next"
      ;;
    testing-hotfix)
      image_tag="hotfix-$(_pollerImageMajor)-next"
      ;;
    esac
  fi

  cat > "${out_file}" <<EOF
# Generated by install-poller $(date -u +%Y-%m-%dT%H:%M:%SZ)

TAG=${image_tag}
# Per-service tag overrides (leave empty to use TAG for all)
ENGINE_TAG=
GORGONE_TAG=
SNMPTRAPD_TAG=
CENTREONTRAPD_TAG=
VMWARE_TAG=

# Build context for centreon-vmware (see --vmware-path)
CENTREON_PLUGINS_PATH=${VMWARE_PATH:-./centreon-plugins}

TZ=${TZ:-UTC}
DEBUG=${DEBUG:-false}

NAME=${POLLER_NAME}
GORGONE_UID=${GORGONE_UID}
CENTRAL_HOST=${GORGONE_ADDRESS}
CENTRAL_PORT=${CENTRAL_PORT}
ENGINE_PORT=${ENGINE_PORT}
GORGONE_TOKEN=${GORGONE_TOKEN}
GORGONE_SSL=${GORGONE_SSL}
GORGONE__GORGONE__MODULES__PULLWSS__CENTRAL_URI=${GORGONE_PULLWSS_CENTRAL_URI}

APP_SECRET=${APP_SECRET}
SALT=${SALT}
EOF

  if [ $? -ne 0 ]; then
    consoleError "Cannot write ${out_file} file."
    logError "Cannot write ${out_file} file."
    exit 1
  fi

  chmod 600 "${out_file}"

  consoleInfo "${out_file} written"
  logInfo "${out_file} written"
}

# Whether a Centreon major version (e.g. 26.10) is an on-prem release.
# On-prem majors always end in .10; anything else is a cloud release. Same
# heuristic as get-environment.yml's cloud/on-prem detection and
# uses_internal_repo() in centreon/unattended.sh (mirrored in
# _usesInternalRepo, src/vm/packages.sh) — shared here so both the Docker and
# VM install paths classify a major the same way.
function _isOnPremMajor() {
  [[ "${1}" == *.10 ]]
}

# The on-prem major this release's containerized pollers come from. On-prem
# majors are used as-is. A cloud major pulls the nearest not-later on-prem
# major, floored at 26.10 (MON-192897: no containerized poller exists before
# that release). MON-208333.
#
# Unlike _usesInternalRepo (VM/RPM path), this doesn't just switch the
# registry at the same major: RPM/DEB packages ARE published for cloud majors
# (to an internal repo, per .github/actions/promote-to-stable), but poller
# container images are only ever published for on-prem majors — so a cloud
# major has to resolve to a *different*, older major here.
function _pollerImageMajor() {
  if _isOnPremMajor "${major}"; then
    echo "${major}"
    return
  fi
  local year=$((10#${major%%.*}))
  local month=$((10#${major##*.}))
  local onprem_year=${year}
  [ "${month}" -lt 10 ] && onprem_year=$((year - 1))
  [ "${onprem_year}" -lt 26 ] && onprem_year=26
  printf "%02d.10" "${onprem_year}"
}

# Registry + repo (no tag) for one of the 5 poller component images, per
# effective stability (see _dockerEffectiveStability). Only 'stable' is
# published to ghcr.io (MON-207531); testing and unstable stay on Harbor, as
# neither ever gets a validated stable-equivalent tag there.
function _pollerImageRepo() {
  local component=$1
  if [ "$(_dockerEffectiveStability)" = "stable" ]; then
    echo "ghcr.io/centreon/centreon-${component}"
  else
    echo "docker.centreon.com/centreon/centreon-${component}-trixie"
  fi
}

function _generateDockerCompose() {
  local dir=$1
  local suffix=${2:-}
  local out="${dir}/docker-compose.yaml${suffix}"
  logInfo "Generating ${out} (vmware=${WITH_VMWARE}, snmptrap=${WITH_SNMPTRAP})"

  # centengine (always)
  cat > "${out}" <<'EOF'
services:
  centengine:
EOF
  printf '    image: "%s:${ENGINE_TAG:-${TAG}}"\n' "$(_pollerImageRepo engine)" >> "${out}"
  cat >> "${out}" <<'EOF'
    container_name: "${NAME}-centengine"
    hostname: centengine
    restart: unless-stopped
    environment:
      NAME: "${NAME}"
      TZ: "${TZ}"
      DEBUG: "${DEBUG}"
      ENGINE_PORT: "${ENGINE_PORT}"
      APP_SECRET: "${APP_SECRET}"
      SALT: "${SALT}"
    volumes:
      - poller-engine:/etc/centreon-engine
      - poller-broker:/etc/centreon-broker
      - poller-centcmd:/var/lib/centreon-engine/rw
      - poller-centlog:/var/log/centreon-engine
EOF

  # Centreon Monitoring Agent (optional): TLS cert mounts + gRPC port
  if [ "${WITH_CMA}" = "1" ]; then
    cat >> "${out}" <<'EOF'
      - ./certs/poller.crt:/etc/pki/poller.crt
      - ./certs/poller.key:/etc/pki/poller.key
    ports:
      - 4317:4317
EOF
  fi

  # centengine healthcheck + dependencies (always)
  cat >> "${out}" <<'EOF'
    healthcheck:
      test: ["CMD-SHELL", "grep -q \":$$(printf '%04X' $${ENGINE_PORT:-443}) 01\" /proc/net/tcp /proc/net/tcp6 2>/dev/null"]
      interval: 30s
      timeout: 5s
      start_period: 5m
      retries: 3
    depends_on:
      gorgone:
        condition: service_healthy
EOF

  # gorgone base volumes (always)
  cat >> "${out}" <<'EOF'
  gorgone:
EOF
  printf '    image: "%s:${GORGONE_TAG:-${TAG}}"\n' "$(_pollerImageRepo gorgone)" >> "${out}"
  cat >> "${out}" <<'EOF'
    container_name: "${NAME}-gorgone"
    hostname: gorgone
    restart: unless-stopped
    environment:
      TZ: "${TZ}"
      DEBUG: "${DEBUG}"
      TYPE: poller
      GORGONE_UID: "${GORGONE_UID}"
      NAME: "${NAME}"
      GORGONE_TOKEN: "${GORGONE_TOKEN}"
      CENTRAL_HOST: "${CENTRAL_HOST}"
      CENTRAL_PORT: "${CENTRAL_PORT}"
      GORGONE__GORGONE__MODULES__PULLWSS__SSL: "${GORGONE_SSL}"
      GORGONE__GORGONE__MODULES__PULLWSS__CENTRAL_URI: "${GORGONE__GORGONE__MODULES__PULLWSS__CENTRAL_URI}"
      APP_SECRET: "${APP_SECRET}"
      SALT: "${SALT}"
    volumes:
      - poller-etc:/etc/centreon/
      - poller-engine:/etc/centreon-engine
      - poller-broker:/etc/centreon-broker
      - poller-centcmd:/var/lib/centreon-engine/rw
      - poller-gorgone-data:/var/lib/centreon-gorgone/
EOF

  # gorgone extra volume for snmptrap
  if [ "${WITH_SNMPTRAP}" = "1" ]; then
    cat >> "${out}" <<'EOF'
      - poller-snmp-traps:/etc/snmp/centreon_traps
EOF
  fi

  # gorgone healthcheck (always, after volumes)
  cat >> "${out}" <<'EOF'
    healthcheck:
      test: ["CMD-SHELL", "grep -q \":$$(printf '%04X' $${CENTRAL_PORT:-443}) 01\" /proc/net/tcp /proc/net/tcp6 2>/dev/null"]
      interval: 30s
      timeout: 5s
      start_period: 30m
      retries: 3
EOF

  printf '\n' >> "${out}"

  # centreon-vmware (optional)
  if [ "${WITH_VMWARE}" = "1" ]; then
    cat >> "${out}" <<'EOF'
  centreon-vmware:
    image: "connector-vmware:${VMWARE_TAG:-local}"
    build:
      context: "${CENTREON_PLUGINS_PATH}"
      dockerfile: .github/docker/connector/Dockerfile.connector-vmware
    container_name: "${NAME}-vmware"
    hostname: centreon-vmware
    restart: unless-stopped
    pull_policy: never
    environment:
      TZ: "${TZ}"
      DEBUG: "${DEBUG}"
      APP_SECRET: "${APP_SECRET}"
      SALT: "${SALT}"
    volumes:
      - poller-etc:/etc/centreon/
    depends_on:
      gorgone:
        condition: service_healthy

EOF
  fi

  # snmptrapd + centreontrapd (optional, always together)
  if [ "${WITH_SNMPTRAP}" = "1" ]; then
    cat >> "${out}" <<'EOF'
  snmptrapd:
EOF
    printf '    image: "%s:${SNMPTRAPD_TAG:-${TAG}}"\n' "$(_pollerImageRepo snmptrapd)" >> "${out}"
    cat >> "${out}" <<'EOF'
    container_name: "${NAME}-snmptrap"
    hostname: snmptrapd
    restart: unless-stopped
    environment:
      TZ: "${TZ}"
      DEBUG: "${DEBUG}"
    volumes:
      - poller-snmp-spool:/var/spool/centreontrapd
    ports:
      - "162:162/udp"
    cap_add:
      - NET_BIND_SERVICE
    healthcheck:
      test: ["CMD-SHELL", "grep -q ':00A2' /proc/net/udp /proc/net/udp6 2>/dev/null"]
      interval: 30s
      timeout: 5s
      start_period: 10s
      retries: 3

  centreontrapd:
EOF
    printf '    image: "%s:${CENTREONTRAPD_TAG:-${TAG}}"\n' "$(_pollerImageRepo centreontrapd)" >> "${out}"
    cat >> "${out}" <<'EOF'
    container_name: "${NAME}-centreontrap"
    hostname: centreontrapd
    restart: unless-stopped
    environment:
      TZ: "${TZ}"
      DEBUG: "${DEBUG}"
    volumes:
      - poller-snmp-spool:/var/spool/centreontrapd
      - poller-snmp-traps:/etc/snmp/centreon_traps:ro
      - poller-centcmd:/var/lib/centreon-engine/rw
    depends_on:
      snmptrapd:
        condition: service_healthy
      gorgone:
        condition: service_healthy

EOF
  fi

  # Base volumes (always)
  cat >> "${out}" <<'EOF'
volumes:
  poller-etc:
  poller-engine:
  poller-broker:
  poller-centcmd:
  poller-centlog:
  poller-gorgone-data:
EOF

  # Volumes pour snmptrap
  if [ "${WITH_SNMPTRAP}" = "1" ]; then
    cat >> "${out}" <<'EOF'
  poller-snmp-spool:
  poller-snmp-traps:
EOF
  fi

  if [ $? -ne 0 ]; then
    consoleError "Cannot write ${out} file."
    logError "Cannot write ${out} file."
    exit 1
  fi

  consoleInfo "${out} written"
  logInfo "${out} written"
}
