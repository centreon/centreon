# Copyright 2026 - present Centreon Team
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

# MON-215450 phase 2g (gorgone-less PoC, contracts.md § 11): Docker poller
# reached through the central broker. A single engine container dials the
# central broker over gRPC TLS; no gorgone, no published port. The central
# broker CA is downloaded from the central and trusted only when its
# fingerprint matches --broker_ca_sha256 (from the installation command),
# before any file is written or any container started.

# In-container path of the CA (outside the broker-etc volume), read by the
# engine bootstrap through BROKER_CA_CERT.
BROKER_CA_CONTAINER_PATH="/etc/centreon-poller/broker-ca.crt"

function runDockerBrokerInstall() {
  echo ""
  consoleMainTitle "Generating Docker Compose files for a Centreon poller (broker channel)"
  logInfo "Broker channel install: poller '${POLLER_NAME}' (uid ${GORGONE_UID}, id ${POLLER_ID}) -> ${BROKER_HOST}:${BROKER_PORT}, CA sha256 ${BROKER_CA_SHA256}"

  consoleTitle "Checking prerequisites:"
  _checkDockerPrerequisites || exit 1
  consoleInfo "compose runner: ${COMPOSE_CMD[*]} (engine: ${CONTAINER_ENGINE}$([ "${ENGINE_ROOTLESS}" = "1" ] && echo ", rootless"))"
  if [ "${CONTAINER_ENGINE}" = "podman" ] && ! _checkPodmanPrerequisites; then
    if [ "${START_STACK}" = "1" ]; then
      exit 1
    fi
    consoleWarn "--no-start given: generating files anyway. Fix the above before starting the stack."
  fi
  echo ""

  # Fail closed before touching anything in the install directory.
  consoleTitle "Verifying the central broker CA:"
  local ca_file=""
  _newTempFile ca_file || exit 1
  _fetchBrokerCa "${ca_file}" || exit 1
  _verifyBrokerCa "${ca_file}" || exit 1
  echo ""

  _resolveOverwriteSuffix "."
  local suffix="${DOCKER_FILE_SUFFIX}"
  if [ "${suffix}" = ".new" ]; then
    if [ -f "./docker-compose.yaml.new" ] || [ -f "./.env.new" ] || [ -f "./broker-ca.crt.new" ]; then
      consoleError "docker-compose.yaml.new/.env.new/broker-ca.crt.new already exist from a previous run. Review/merge or remove them before re-running."
      logError "Refusing to overwrite existing docker-compose.yaml.new/.env.new/broker-ca.crt.new."
      exit 1
    fi
    # Old files are untouched; starting the stack would run stale ones.
    START_STACK=0
  fi

  _writeBrokerCa "${ca_file}" "./broker-ca.crt${suffix}"
  _generateBrokerDotEnv "." "${suffix}"
  _generateSmtpDotEnv "."
  _generateBrokerDockerCompose "." "${suffix}"

  echo ""
  consoleTitle "Files generated:"
  consoleInfo "  docker-compose.yaml${suffix}"
  consoleInfo "  .env${suffix} (mode 0600: holds the application secret and salt)"
  consoleInfo "  broker-ca.crt${suffix} (central broker CA, verified)"
  consoleInfo "  .env.smtp (created empty if missing, never overwritten — fill in SMTP_HOST/SMTP_PORT/SMTP_FROM/SMTP_TLS for native SMTP notifications)"
  echo ""
  consoleTitle "Services included:"
  consoleInfo "  centengine (connects to the central broker at ${BROKER_HOST}:${BROKER_PORT})"
  if [ "${WITH_CMA}" = "1" ]; then
    consoleInfo "  Centreon Monitoring Agent support (--with-cma): persisted CMA CA + port 4317"
  fi
  echo ""

  if [ "${suffix}" = ".new" ]; then
    consoleTitle "Next steps:"
    echo "  Existing files were left untouched. Review and merge manually, e.g.:"
    echo "       diff docker-compose.yaml docker-compose.yaml.new"
    echo "       diff .env .env.new"
    echo "  Adopt docker-compose.yaml.new, .env.new and broker-ca.crt.new together."
    echo ""
    return
  fi

  if [ "${START_STACK}" = "1" ]; then
    echo ""
    consoleTitle "Starting stack:"
    local rc=0
    "${COMPOSE_CMD[@]}" up -d || rc=$?
    if [ "${rc}" -ne 0 ]; then
      consoleError "Failed to start stack."
      logError "${COMPOSE_CMD[*]} up -d failed (exit ${rc})"
      exit 1
    fi
    echo ""
  else
    consoleTitle "Next steps:"
    echo "  1. Start the stack:"
    echo "       ${COMPOSE_CMD[*]} up -d"
    echo ""
  fi

  if [ "${CONTAINER_ENGINE}" = "podman" ]; then
    _printPodmanRebootHint
  fi
}

# mktemp file, mode 0600, removed on exit (exitMain). Never under log/.
# Sets the variable named $1 (not echoed: TMP_FILES must be updated in this
# shell, not in a command substitution subshell).
function _newTempFile() {
  local var_name=$1
  local file
  if ! file=$(mktemp "${TMPDIR:-/tmp}/centreon-install-poller.XXXXXX"); then
    consoleError "Cannot create a temporary file."
    logError "mktemp failed."
    return 1
  fi
  chmod 600 "${file}"
  TMP_FILES+=("${file}")
  printf -v "${var_name}" '%s' "${file}"
}

# Downloads the CA with the poller token. The token goes through a header file
# (curl -H @file) so that it never shows in the process list, and is never
# printed or logged.
function _fetchBrokerCa() {
  local out_file=$1
  local url="${CENTRAL_URL%/}/api/latest/configuration/pollers/${GORGONE_UID}/broker-ca"
  local header_file="" body_file=""
  _newTempFile header_file || return 1
  _newTempFile body_file || return 1

  printf 'X-Centreon-Poller-Token: %s\n' "${GORGONE_TOKEN}" > "${header_file}"

  local http_code="000"
  http_code=$(curl -sS --max-time 30 -o "${body_file}" -w '%{http_code}' \
    -H @"${header_file}" -H 'Accept: application/json' "${url}" 2>>"${LOG_FILE}") || http_code="000"
  logInfo "GET ${url}: HTTP ${http_code}"

  case "${http_code}" in
  200) ;;
  401)
    consoleError "The central rejected the poller token (HTTP 401). Check that it is valid, not revoked nor expired."
    return 1
    ;;
  404)
    consoleError "The central knows no poller with uid ${GORGONE_UID} (HTTP 404). Regenerate the installation command."
    return 1
    ;;
  503)
    consoleError "The central broker CA is not available yet (HTTP 503). Retry once the central broker is running."
    return 1
    ;;
  000)
    consoleError "Cannot reach the central at ${CENTRAL_URL} to download the broker CA (see ${LOG_FILE})."
    return 1
    ;;
  *)
    consoleError "Unexpected answer from the central while downloading the broker CA (HTTP ${http_code})."
    return 1
    ;;
  esac

  # JSON without jq or python: the PEM is a JSON string whose only escapes are
  # \n and, depending on the encoder, \/ (base64 contains '/').
  local pem
  pem=$(tr -d '\r\n' < "${body_file}" | sed -n 's/.*"pem"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p')
  if [ -z "${pem}" ]; then
    consoleError "The central answered without a CA certificate."
    logError "No pem field in the broker-ca answer."
    return 1
  fi
  printf '%s\n' "${pem}" | sed 's#\\/#/#g' | awk '{ gsub(/\\n/, "\n"); printf "%s", $0 }' > "${out_file}"
  printf '\n' >> "${out_file}"

  if ! grep -q -- '-----BEGIN CERTIFICATE-----' "${out_file}"; then
    consoleError "The central answered with an invalid CA certificate."
    logError "The broker-ca answer is not a PEM certificate."
    return 1
  fi
}

# SHA-256 of the DER of the first certificate: the value of cbd GET /v1/ca and
# of the installation command (contracts.md § 11.C). Coreutils only.
function _brokerCaFingerprint() {
  local ca_file=$1
  awk '/-----BEGIN CERTIFICATE-----/{p=1;next} /-----END CERTIFICATE-----/{if(p)exit} p' "${ca_file}" |
    tr -d ' \r\n' | base64 -d 2>/dev/null | sha256sum | cut -d' ' -f1
}

function _verifyBrokerCa() {
  local ca_file=$1
  local actual
  actual=$(_brokerCaFingerprint "${ca_file}")
  if [ "${actual}" != "${BROKER_CA_SHA256}" ]; then
    consoleError "Central broker CA fingerprint mismatch: expected ${BROKER_CA_SHA256}, got ${actual:-none}."
    consoleError "Refusing to install: nothing was written and no container was started."
    logError "CA fingerprint mismatch: expected ${BROKER_CA_SHA256}, got ${actual:-none}"
    return 1
  fi
  consoleInfo "central broker CA verified (sha256 ${actual})"
  logInfo "CA fingerprint verified (${actual})"
}

# Public material, read by the container user (901) through the bind mount.
function _writeBrokerCa() {
  local ca_file=$1
  local out_file=$2
  if ! cp "${ca_file}" "${out_file}" || ! chmod 0644 "${out_file}"; then
    consoleError "Cannot write ${out_file}."
    logError "Cannot write ${out_file}."
    exit 1
  fi
  consoleInfo "${out_file} written"
  logInfo "${out_file} written"
}

# .env of the broker channel (contracts.md § 11.C): created with mode 0600
# (umask scoped to the write; an existing file is removed first, a redirection
# keeps its mode). Free-form values are single-quoted: compose reads them
# verbatim (_installValidateBrokerArgs refused quotes, '$' and backslashes).
function _generateBrokerDotEnv() {
  local dir=$1
  local suffix=${2:-}
  local out_file="${dir}/.env${suffix}"
  local image_tag
  image_tag="$(_dockerImageTag)"
  logInfo "Generating ${out_file} (stability: $(_dockerEffectiveStability), broker channel)"

  rm -f "${out_file}"
  if ! (
    umask 077
    cat > "${out_file}" <<EOF
# Generated by install-poller $(date -u +%Y-%m-%dT%H:%M:%SZ) (broker channel)

TAG=${image_tag}
# Resolved here rather than with a nested \${ENGINE_TAG:-\${TAG}} in
# docker-compose.yaml, which podman-compose leaves unexpanded.
ENGINE_TAG=\${TAG}

TZ=${TZ:-UTC}
DEBUG=${DEBUG:-false}

NAME='${POLLER_NAME}'
POLLER_NAME='${POLLER_NAME}'
POLLER_UID=${GORGONE_UID}
POLLER_ID=${POLLER_ID}

# Central broker the engine dials (gRPC TLS). The name checked against the
# central certificate defaults to the host.
CENTRAL_BROKER_HOST=${BROKER_HOST}
CENTRAL_BROKER_PORT=${BROKER_PORT}
EOF
    if [ -n "${BROKER_TLS_HOSTNAME}" ]; then
      echo "BROKER_TLS_HOSTNAME=${BROKER_TLS_HOSTNAME}" >> "${out_file}"
    fi
    cat >> "${out_file}" <<EOF
BROKER_CA_CERT=${BROKER_CA_CONTAINER_PATH}
BROKER_CA_FINGERPRINT='${BROKER_CA_SHA256}'

# centengine hostname = CN of the CMA CA, generated once into the poller-cma-pki
# volume. Must match the poller address agents connect to.
ENGINE_HOSTNAME=${CMA_HOSTNAME:-centengine}

APP_SECRET='${APP_SECRET}'
SALT='${SALT}'
EOF
  ); then
    consoleError "Cannot write ${out_file} file."
    logError "Cannot write ${out_file} file."
    exit 1
  fi

  consoleInfo "${out_file} written"
  logInfo "${out_file} written"
}

function _generateBrokerDockerCompose() {
  local dir=$1
  local suffix=${2:-}
  local out="${dir}/docker-compose.yaml${suffix}"
  local ca_mount_options="ro"
  if [ "${CONTAINER_ENGINE}" = "podman" ]; then
    # SELinux hosts: let the container read the bind-mounted file.
    ca_mount_options="ro,z"
  fi
  logInfo "Generating ${out} (broker channel, cma=${WITH_CMA}, network=${DOCKER_NETWORK:-none}, extra hosts=${#ADD_HOSTS[@]})"

  {
    echo "services:"
    echo "  centengine:"
    printf '    image: "%s:${ENGINE_TAG:?set ENGINE_TAG in .env}"\n' "$(_pollerImageRepo engine)"
    cat <<'EOF'
    env_file:
      - .env
      - .env.smtp
    container_name: "${NAME}-centengine"
    hostname: "${ENGINE_HOSTNAME:-centengine}"
    restart: unless-stopped
    cap_add:
      - NET_RAW
    volumes:
      - poller-engine:/etc/centreon-engine
      - poller-broker:/etc/centreon-broker
      - poller-engine-state:/var/lib/centreon-engine
EOF
    echo "      - ./broker-ca.crt:${BROKER_CA_CONTAINER_PATH}:${ca_mount_options}"
    if [ "${WITH_CMA}" = "1" ]; then
      echo "      - poller-cma-pki:/etc/pki/centreon-engine"
    fi
    cat <<'EOF'
    tmpfs:
      - /var/lib/centreon-engine/rw:uid=901,gid=901,mode=0770
EOF
    if [ "${WITH_CMA}" = "1" ]; then
      echo "    ports:"
      echo "      - 4317:4317"
    fi
    if [ ${#ADD_HOSTS[@]} -gt 0 ]; then
      echo "    extra_hosts:"
      local add_host
      for add_host in "${ADD_HOSTS[@]}"; do
        echo "      - \"${add_host%%:*}:${add_host#*:}\""
      done
    fi
    if [ -n "${DOCKER_NETWORK}" ]; then
      echo "    networks:"
      echo "      - default"
      echo "      - ${DOCKER_NETWORK}"
    fi
    echo ""
    if [ -n "${DOCKER_NETWORK}" ]; then
      echo "networks:"
      echo "  ${DOCKER_NETWORK}:"
      echo "    external: true"
      echo ""
    fi
    echo "volumes:"
    echo "  poller-engine:"
    echo "  poller-broker:"
    echo "  poller-engine-state:"
    if [ "${WITH_CMA}" = "1" ]; then
      echo "  poller-cma-pki:"
    fi
  } > "${out}"

  if [ $? -ne 0 ]; then
    consoleError "Cannot write ${out} file."
    logError "Cannot write ${out} file."
    exit 1
  fi

  consoleInfo "${out} written"
  logInfo "${out} written"
}
