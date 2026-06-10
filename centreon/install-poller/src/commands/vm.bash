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
