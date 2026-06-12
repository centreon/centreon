#!/bin/bash
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
# Centreon poller installer - 26.07

# Centreon major version
major="26.07"

## Configuration variable
LOG_FILE="./log/install-poller.log"

# Deployment type: docker | vm
POLLER_TYPE="docker"

# Cloud mode: true (Centreon Cloud) | false (on-prem, future)
CLOUD_MODE="true"

# Docker mode args
POLLER_TOKEN=""
POLLER_UID=""
POLLER_NAME=""
CENTRAL_URL=""
APP_SECRET=""
SALT=""

# Optional services (Docker mode)
WITH_VMWARE=0
WITH_SNMPTRAP=0
START_STACK=1

MAX_CHARACTERS=76
COLUMNS=$(tput cols 2>/dev/null || echo 80)

if [ "${COLUMNS}" != "" ] && [ ${COLUMNS} -lt 80 ]; then
  MAX_CHARACTERS=$((COLUMNS-4))
fi

function errorColor() {
  printf "\e[91m\e[1m$*\e[0m"
}

function warnColor() {
  printf "\e[93m\e[1m$*\e[0m"
}

function infoColor() {
  printf "\e[94m\e[1m$*\e[0m"
}

function debugColor() {
  printf "\e[1m$*\e[0m"
}

function boldColor() {
  printf "\e[1m$*\e[0m"
}

function consoleError() {
  errorColor "ERROR "
  echo $*
}

function consoleWarn() {
  warnColor "WARN  "
  echo $*
}

function consoleInfo() {
  infoColor "INFO  "
  echo $*
}

function consoleMainTitle() {
  local msgSize=$(echo "$*" | wc -c)
  boldColor "#### $* ####"
  echo ""
}

function consoleTitle() {
  local msgSize=$(echo "$*" | wc -c)
  boldColor $*
  echo ""
}

MAX_LOG_RETENTION=3

function initLog() {
  local filename=$1
  filename="${filename#"${filename%%[![:space:]]*}"}"
  filename="${filename%"${filename##*[![:space:]]}"}"
  if [ "${filename}" = "" ]; then
    echo -e "[$(errorColor ERROR)]The log filename is empty."
    exit 1
  fi

  local logdir=$(dirname "${filename}")
  if [ ! -d "${logdir}" ]; then
    echo -e "[$(errorColor ERROR)]The log directory doesn't exists."
    exit 1
  fi

  if [ -f "${filename}" ]; then
    rotateLog "${filename}"
  fi

  exec 7>"${filename}"

  exec 6>&2
  exec 2> >(stderrLog)
}

function closeLog() {
  exec 7>&-
  exec 2>&6 6>&-
}

function rotateLog() {
  local filename=$1

  for idx in $(seq $((${MAX_LOG_RETENTION}-1)) -1 1); do
    if [ -f "${filename}.${idx}" ]; then
      mv "${filename}.${idx}" "${filename}.$((${idx}+1))"
    fi
  done
  mv "${filename}" "${filename}.1"
}

function stderrLog() {
  while IFS= read -r msg; do
    logTrace ${msg}
  done
}

function logError() {
  logMessage "ERROR" $*
}

function logWarn() {
  logMessage "WARN" $*
}

function logInfo() {
  logMessage "INFO" $*
}

function logDebug() {
  logMessage "DEBUG" $*
}

function logTrace() {
  logMessage "TRACE" $*
}

function logMessage() {
  local level=$1
  shift
  printf "%s - %-5s - " "$(date -u +"%Y-%m-%dT%H:%M:%SZ")" "${level}" >&7
  echo $* >&7
}

function commandExitOnError() {
  local error_msg=$1
  shift
  "$@"
  if [ $? -ne 0 ]; then
    logError "${error_msg}"
    consoleError "${error_msg}"
    exit 1
  fi
}

function cmdParse() {
  cmd=$*
  subcommand=$(echo "${cmd}" | sed -r "s/^(install)?.*$/\1/g")
  arguments=${cmd#"$subcommand"}

  if helpFlag ${arguments}; then
    echo "help ${subcommand}"
    return
  fi

  case "${subcommand}" in
  install)
    echo "installCommand ${arguments}"
    ;;
  *)
    echo "help"
    ;;
  esac
}

function helpFlag() {
  for arg in "$@"; do
    if [ "${arg}" = "-h" ] || [ "${arg}" = "--help" ]; then
      return 0
    fi
  done

  return 1
}

PROG_NAME="install-poller"

function help() {
  local subcommand=${1:-"[command]"}

  echo ""
  echo "The ${PROG_NAME} installs and configures a Centreon poller." | fold -s -w ${MAX_CHARACTERS}
  echo ""
  echo "Usage:"
  echo "  ${PROG_NAME} ${subcommand}"
  echo ""

  case ${subcommand} in
  install)
    echo "Flags:"
    echo -e "  --type string\t\t\tDeployment type: docker (default) | vm"
    echo -e "  --cloud bool\t\t\tCloud mode: true (default) | false (on-prem)"
    echo ""
    echo "Required flags (all types):"
    echo -e "  --poller_token string\t\tGorgone poller token"
    echo -e "  --uid string\t\t\tPoller unique ID"
    echo -e "  --name string\t\t\tPoller name"
    echo -e "  --central_url string\t\tCentral URL (cloud: avvcentreon.euwest1.centreon.cloud | on-prem: host:port)"
    echo -e "  --appsecret string\t\tApplication secret"
    echo -e "  --salt string\t\t\tEncryption salt"
    echo ""
    echo "Docker behavior flags:"
    echo -e "  --no-start\t\t\tOnly generate files, do not run docker compose up -d"
    echo ""
    echo "Docker optional flags:"
    echo -e "  --tz string\t\t\tTimezone (default: UTC)"
    echo -e "  --debug bool\t\t\tEnable debug logging (default: false)"
    echo -e "  --gorgone-ssl bool\t\tEnable SSL for Gorgone (default depends on --cloud)"
    echo -e "  --with-vmware\t\t\tInclude centreon-vmware service"
    echo -e "  --with-snmptrap\t\tInclude snmptrapd and centreontrapd services"
    echo ""
    echo "Notes:"
    echo -e "  --cloud true\t\t\tGorgone address auto-derived: gorgone-centreon-<central_url>, port 443, ssl=true"
    echo -e "  --cloud false\t\t\tGorgone address from --central_url (host:port), ssl=false (override with --gorgone-ssl)"
    ;;
  *)
    echo "Available commands:"
    echo "  install  Install and configure the poller"
    ;;
  esac
  echo ""
  echo "Global flags:"
  echo -e "  -h, --help\tDisplay the help of the command"
}

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

function runDockerInstall() {
  echo ""
  consoleMainTitle "Generating Docker Compose files for Centreon poller"

  consoleTitle "Checking prerequisites:"
  _checkDockerPrerequisites || exit 1
  consoleInfo "docker and docker compose are available"
  echo ""

  _generateDotEnv "."
  _generateDockerCompose "."

  echo ""
  consoleTitle "Files generated:"
  consoleInfo "  docker-compose.yaml"
  consoleInfo "  .env"
  echo ""
  consoleTitle "Services included:"
  consoleInfo "  centengine, gorgone (always)"
  if [ "${WITH_VMWARE}" = "1" ]; then
    consoleInfo "  centreon-vmware (--with-vmware)"
  fi
  if [ "${WITH_SNMPTRAP}" = "1" ]; then
    consoleInfo "  snmptrapd, centreontrapd (--with-snmptrap)"
  fi
  echo ""
  if [ "${START_STACK}" = "1" ]; then
    echo ""
    consoleTitle "Starting stack:"
    docker compose up -d
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

function _generateDotEnv() {
  local dir=$1
  logInfo "Generating .env in ${dir}"

  cat > "${dir}/.env" <<EOF
# Generated by install-poller $(date -u +%Y-%m-%dT%H:%M:%SZ)

TAG=${major}
# Per-service tag overrides (leave empty to use TAG for all)
ENGINE_TAG=
GORGONE_TAG=
SNMPTRAPD_TAG=
CENTREONTRAPD_TAG=
VMWARE_TAG=

TZ=${TZ:-UTC}
DEBUG=${DEBUG:-false}

NAME=${POLLER_NAME}
GORGONE_UID=${GORGONE_UID}
CENTRAL_HOST=${CENTRAL_HOST}
CENTRAL_PORT=${CENTRAL_PORT:-443}
GORGONE_TOKEN=${GORGONE_TOKEN}
GORGONE_SSL=${GORGONE_SSL:-$([ "${CLOUD_MODE}" = "false" ] && echo "false" || echo "true")}

APP_SECRET=${APP_SECRET}
SALT=${SALT}
EOF

  if [ $? -ne 0 ]; then
    consoleError "Cannot write .env file."
    logError "Cannot write .env file."
    exit 1
  fi

  consoleInfo ".env written"
  logInfo ".env written"
}

function _generateDockerCompose() {
  local dir=$1
  local out="${dir}/docker-compose.yaml"
  logInfo "Generating docker-compose.yaml in ${dir} (vmware=${WITH_VMWARE}, snmptrap=${WITH_SNMPTRAP})"

  # centengine (always)
  cat > "${out}" <<'EOF'
services:
  centengine:
    image: "docker.centreon.com/centreon/centreon-engine-trixie:${ENGINE_TAG:-${TAG}}"
    container_name: "${NAME}-centengine"
    hostname: centengine
    restart: unless-stopped
    environment:
      NAME: "${NAME}"
      TZ: "${TZ}"
      DEBUG: "${DEBUG}"
      APP_SECRET: "${APP_SECRET}"
      SALT: "${SALT}"
    volumes:
      - poller-engine:/etc/centreon-engine
      - poller-broker:/etc/centreon-broker
      - poller-centcmd:/var/lib/centreon-engine/rw
      - poller-centlog:/var/log/centreon-engine
      - ./certs/poller.crt:/etc/pki/poller.crt
      - ./certs/poller.key:/etc/pki/poller.key
    ports:
      - 4317:4317
    healthcheck:
      test: ["CMD-SHELL", "grep -q ':1625 01' /proc/net/tcp 2>/dev/null"]
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
    image: "docker.centreon.com/centreon/centreon-gorgone-trixie:${GORGONE_TAG:-${TAG}}"
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
    image: "docker.centreon.com/centreon/centreon-snmptrapd-trixie:${SNMPTRAPD_TAG:-${TAG}}"
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
    image: "docker.centreon.com/centreon/centreon-centreontrapd-trixie:${CENTREONTRAPD_TAG:-${TAG}}"
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
    consoleError "Cannot write docker-compose.yaml file."
    logError "Cannot write docker-compose.yaml file."
    exit 1
  fi

  consoleInfo "docker-compose.yaml written"
  logInfo "docker-compose.yaml written"
}

_PKG_COMMAND=""

function runVmInstall() {
  echo ""
  consoleMainTitle "Installing Centreon poller"

  consoleTitle "Checking prerequisites:"
  _vmCheckRoot
  _vmDetectDistribution
  _vmCheckRam
  _vmCheckNetwork

  echo ""
  consoleTitle "Installing Centreon packages:"
  _vmInstallPowertools
  _vmInstallRepo
  _vmInstallPackages

  echo ""
  consoleTitle "Configuring poller:"
  _vmConfigureGorgone
  _vmConfigureEngineContext

  echo ""
  consoleTitle "Starting services:"
  _vmEnableServices
  _vmStartServices

  echo ""
  consoleInfo "Poller successfully installed and started."
}

function _vmCheckRoot() {
  if [ "$(id -u)" -ne 0 ]; then
    consoleError "This script must be run as root."
    logError "Script not run as root."
    exit 1
  fi
  consoleInfo "Running as root: OK"
  logInfo "Root check: OK"
}

function _vmDetectDistribution() {
  if [ ! -f /etc/os-release ]; then
    consoleError "Cannot detect distribution (/etc/os-release not found)."
    logError "Cannot detect distribution."
    exit 1
  fi

  . /etc/os-release
  local distrib="${ID}${VERSION_ID}"

  case "${distrib}" in
    "debian12"*)
      _PKG_COMMAND="apt"
      consoleInfo "Distribution: Debian 12 (Bookworm): OK"
      logInfo "Distribution: debian12, package manager: apt"
      ;;
    "almalinux9"*)
      _PKG_COMMAND="dnf"
      consoleInfo "Distribution: AlmaLinux 9: OK"
      logInfo "Distribution: almalinux9, package manager: dnf"
      ;;
    "ol9"*)
      _PKG_COMMAND="dnf"
      consoleInfo "Distribution: Oracle Linux 9: OK"
      logInfo "Distribution: ol9, package manager: dnf"
      ;;
    "rhel9"*)
      _PKG_COMMAND="dnf"
      consoleInfo "Distribution: RHEL 9: OK"
      logInfo "Distribution: rhel9, package manager: dnf"
      ;;
    *)
      consoleError "Unsupported distribution: ${PRETTY_NAME:-${distrib}}."
      consoleError "Supported: Debian 12, AlmaLinux 9, Oracle Linux 9, RHEL 9."
      logError "Unsupported distribution: ${distrib}"
      exit 1
      ;;
  esac
}

function _vmCheckRam() {
  local ram_kb
  ram_kb=$(grep MemTotal /proc/meminfo | awk '{print $2}')
  local ram_mb=$(( ram_kb / 1024 ))
  if [ "${ram_mb}" -lt 1536 ]; then
    consoleError "Insufficient RAM: ${ram_mb} MB. Minimum required: 1536 MB."
    logError "Insufficient RAM: ${ram_mb} MB."
    exit 1
  fi
  consoleInfo "RAM: ${ram_mb} MB: OK"
  logInfo "RAM check: ${ram_mb} MB OK"
}

function _vmCheckNetwork() {
  local target
  if [ "${CLOUD_MODE}" = "true" ]; then
    target="https://${CENTRAL_URL}"
  else
    local host
    host=$(echo "${CENTRAL_URL}" | cut -d: -f1)
    target="http://${host}"
  fi

  if curl -sf --max-time 10 "${target}" >/dev/null 2>&1; then
    consoleInfo "Network connectivity to ${CENTRAL_URL}: OK"
    logInfo "Network check to ${target}: OK"
  else
    consoleWarn "Cannot reach ${target} — check network connectivity before starting services."
    logWarn "Network check to ${target}: failed"
  fi
}

function _vmInstallPowertools() {
  [ "${_PKG_COMMAND}" != "dnf" ] && return

  . /etc/os-release
  local distrib="${ID}${VERSION_ID}"

  consoleInfo "Installing EPEL and CRB/Powertools"
  logInfo "Installing EPEL and CRB for ${distrib}"

  case "${distrib}" in
    "almalinux9"*)
      commandExitOnError "Cannot install dnf-plugins-core and epel-release" \
        dnf -y install dnf-plugins-core epel-release
      commandExitOnError "Cannot enable CRB" \
        dnf config-manager --set-enabled crb
      ;;
    "ol9"*)
      commandExitOnError "Cannot install dnf-plugins-core" \
        dnf -y install dnf-plugins-core
      commandExitOnError "Cannot install EPEL for Oracle Linux 9" \
        dnf -y install https://dl.fedoraproject.org/pub/epel/epel-release-latest-9.noarch.rpm
      commandExitOnError "Cannot enable OL9 CodeReady Builder" \
        dnf config-manager --set-enabled ol9_codeready_builder
      commandExitOnError "Cannot install epel-release" \
        dnf -y install epel-release
      ;;
    "rhel9"*)
      commandExitOnError "Cannot install dnf-plugins-core" \
        dnf -y install dnf-plugins-core
      commandExitOnError "Cannot install EPEL for RHEL 9" \
        dnf -y install https://dl.fedoraproject.org/pub/epel/epel-release-latest-9.noarch.rpm
      commandExitOnError "Cannot enable RHEL 9 CRB" \
        subscription-manager repos --enable codeready-builder-for-rhel-9-x86_64-rpms
      ;;
  esac
}

function _vmInstallRepo() {
  logInfo "Adding Centreon ${major} repository"

  if [ "${_PKG_COMMAND}" = "apt" ]; then
    consoleInfo "Adding Centreon repository (Debian)"
    commandExitOnError "Cannot update apt" \
      apt update
    commandExitOnError "Cannot install repo prerequisites" \
      env DEBIAN_FRONTEND=noninteractive apt install -y lsb-release ca-certificates wget gnupg2

    consoleInfo "Importing Centreon GPG key"
    commandExitOnError "Cannot import Centreon GPG key" \
      bash -c 'wget -O- https://apt-key.centreon.com | gpg --dearmor | tee /etc/apt/trusted.gpg.d/centreon.gpg > /dev/null 2>&1'

    local codename
    codename=$(lsb_release -sc)
    echo "deb https://packages.centreon.com/apt-standard/ ${codename}-${major}-stable main" \
      | tee /etc/apt/sources.list.d/centreon-stable.list > /dev/null
    echo "deb https://packages.centreon.com/apt-plugins-stable/ ${codename} main" \
      | tee /etc/apt/sources.list.d/centreon-plugins.list > /dev/null

    commandExitOnError "Cannot update apt repositories after adding Centreon repo" \
      apt update

  elif [ "${_PKG_COMMAND}" = "dnf" ]; then
    consoleInfo "Adding Centreon repository (RPM/EL9)"
    commandExitOnError "Cannot add Centreon repository" \
      dnf config-manager --add-repo "https://packages.centreon.com/rpm-standard/${major}/el9/centreon-${major}.repo"
  fi
}

function _vmInstallPackages() {
  consoleInfo "Installing Centreon poller packages"
  logInfo "Installing centreon-poller and jq"

  if [ "${_PKG_COMMAND}" = "apt" ]; then
    commandExitOnError "Cannot install Centreon poller packages" \
      env DEBIAN_FRONTEND=noninteractive apt install -y centreon-poller jq
  elif [ "${_PKG_COMMAND}" = "dnf" ]; then
    commandExitOnError "Cannot install Centreon poller packages" \
      dnf install -y centreon-poller jq
  fi
}

function _vmConfigureGorgone() {
  consoleInfo "Writing gorgone configuration"
  logInfo "Configuring /etc/centreon-gorgone/config.d/40-gorgoned.yaml"

  local central_host central_port ssl_mode

  if [ "${CLOUD_MODE}" = "true" ]; then
    central_host="gorgone-centreon-${CENTRAL_URL}"
    central_port=443
    ssl_mode="true"
  else
    if echo "${CENTRAL_URL}" | grep -q ":"; then
      central_host=$(echo "${CENTRAL_URL}" | cut -d: -f1)
      central_port=$(echo "${CENTRAL_URL}" | cut -d: -f2)
    else
      central_host="${CENTRAL_URL}"
      central_port=8086
    fi
    ssl_mode="false"
  fi

  mkdir -p /etc/centreon-gorgone/config.d
  local old_umask
  old_umask="$(umask)"
  umask 027
  cat > /etc/centreon-gorgone/config.d/40-gorgoned.yaml <<EOF
name: ${POLLER_NAME}
description: Poller configuration
gorgone:
  gorgonecore:
    id: ${GORGONE_UID}
    privkey: /var/lib/centreon-gorgone/.keys/rsakey.priv.pem
    pubkey: /var/lib/centreon-gorgone/.keys/rsakey.pub.pem

  modules:
    - name: engine
      package: gorgone::modules::centreon::engine::hooks
      enable: true
      command_file: "/var/lib/centreon-engine/rw/centengine.cmd"

    - name: pullwss
      package: "gorgone::modules::core::pullwss::hooks"
      enable: true
      ssl: ${ssl_mode}
      port: ${central_port}
      token: ${GORGONE_TOKEN}
      address: ${central_host}
EOF
  local ret=$?
  umask "${old_umask}"

  if [ ${ret} -ne 0 ]; then
    consoleError "Cannot write gorgone configuration."
    logError "Cannot write /etc/centreon-gorgone/config.d/40-gorgoned.yaml"
    exit 1
  fi

  chown centreon-gorgone:centreon-gorgone /etc/centreon-gorgone/config.d/40-gorgoned.yaml
  consoleInfo "Gorgone configuration written"
  logInfo "Gorgone configuration written (address: ${central_host}:${central_port}, ssl: ${ssl_mode})"
}

function _vmConfigureEngineContext() {
  consoleInfo "Writing engine context"
  logInfo "Configuring /etc/centreon-engine/engine-context.json"

  mkdir -p /etc/centreon-engine
  local old_umask
  old_umask="$(umask)"
  umask 006
  printf '{"app_secret":"%s","salt":"%s"}' "${APP_SECRET}" "${SALT}" \
    > /etc/centreon-engine/engine-context.json
  local ret=$?
  umask "${old_umask}"

  if [ ${ret} -ne 0 ]; then
    consoleError "Cannot write engine context."
    logError "Cannot write /etc/centreon-engine/engine-context.json"
    exit 1
  fi

  chown root:centreon-engine /etc/centreon-engine/engine-context.json
  consoleInfo "Engine context written"
  logInfo "Engine context written"
}

function _vmEnableServices() {
  consoleInfo "Enabling services"
  commandExitOnError "Cannot enable Centreon services" \
    systemctl enable centengine gorgoned centreontrapd snmptrapd
}

function _vmStartServices() {
  consoleInfo "Starting gorgoned"
  commandExitOnError "Cannot start gorgoned" \
    systemctl restart gorgoned

  consoleInfo "Starting centengine"
  commandExitOnError "Cannot start centengine" \
    systemctl restart centengine

  consoleInfo "Starting snmptrapd"
  commandExitOnError "Cannot start snmptrapd" \
    systemctl restart snmptrapd

  consoleInfo "Starting centreontrapd"
  commandExitOnError "Cannot start centreontrapd" \
    systemctl restart centreontrapd
}

function installCommand() {
  _installParseArguments $*
  _installValidateArgs || exit 1

  case "${POLLER_TYPE}" in
  docker)
    runDockerInstall
    ;;
  vm)
    runVmInstall
    ;;
  *)
    consoleError "Unknown --type value: '${POLLER_TYPE}'. Valid values: docker, vm."
    exit 1
    ;;
  esac
}

function _installParseArguments() {
  while [ "$*" != "" ]; do
    case $1 in
    --type)
      shift
      POLLER_TYPE=$1
      ;;
    --cloud)
      shift
      CLOUD_MODE=$1
      ;;
    --poller_token)
      shift
      GORGONE_TOKEN=$1
      ;;
    --uid)
      shift
      GORGONE_UID=$1
      ;;
    --name)
      shift
      POLLER_NAME=$1
      ;;
    --central_url)
      shift
      CENTRAL_URL=$1
      _url_no_scheme=$(echo "${CENTRAL_URL}" | sed 's|^https\?://||')
      CENTRAL_HOST=$(echo "${_url_no_scheme}" | cut -d: -f1 | cut -d/ -f1)
      _port=$(echo "${_url_no_scheme}" | cut -s -d: -f2 | cut -d/ -f1)
      CENTRAL_PORT=${_port:-443}
      ;;
    --appsecret)
      shift
      APP_SECRET=$1
      ;;
    --salt)
      shift
      SALT=$1
      ;;
    --tz)
      shift
      TZ=$1
      ;;
    --debug)
      shift
      DEBUG=$1
      ;;
    --gorgone-ssl)
      shift
      GORGONE_SSL=$1
      ;;
    --no-start)
      START_STACK=0
      ;;
    --with-vmware)
      WITH_VMWARE=1
      ;;
    --with-snmptrap)
      WITH_SNMPTRAP=1
      ;;
    esac
    shift
  done
}

function _installValidateArgs() {
  local ret=0

  case "${POLLER_TYPE}" in
  docker|vm)
    if [ -z "${GORGONE_TOKEN}" ]; then
      consoleError "--poller_token is required."
      ret=1
    fi
    if [ -z "${GORGONE_UID}" ]; then
      consoleError "--uid is required."
      ret=1
    fi
    if [ -z "${POLLER_NAME}" ]; then
      consoleError "--name is required."
      ret=1
    fi
    if [ -z "${CENTRAL_URL}" ]; then
      consoleError "--central_url is required."
      ret=1
    fi
    if [ -z "${APP_SECRET}" ]; then
      consoleError "--appsecret is required."
      ret=1
    fi
    if [ -z "${SALT}" ]; then
      consoleError "--salt is required."
      ret=1
    fi
    ;;
  esac

  return ${ret}
}
LANG=C
LC_CTYPE=C

function exitMain() {
  logInfo Exit the program
  closeLog
}

trap exitMain EXIT

if ! mkdir -p "$(dirname "${LOG_FILE}")"; then
  consoleError Cannot create the directory for log file "($(dirname "${LOG_FILE}"))".
fi

if ! type curl >/dev/null 2>&1; then
  consoleError The binary curl must be installed.
  exit 1
fi

initLog "${LOG_FILE}"

subcommand=$(cmdParse $*)
eval ${subcommand}
