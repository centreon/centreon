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
_EL_MAJOR=""

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
    "debian13"*)
      _PKG_COMMAND="apt"
      consoleInfo "Distribution: Debian 13 (Trixie): OK"
      logInfo "Distribution: debian13, package manager: apt"
      ;;
    "almalinux9"*)
      _PKG_COMMAND="dnf"
      _EL_MAJOR="9"
      consoleInfo "Distribution: AlmaLinux 9: OK"
      logInfo "Distribution: almalinux9, package manager: dnf"
      ;;
    "almalinux10"*)
      _PKG_COMMAND="dnf"
      _EL_MAJOR="10"
      consoleInfo "Distribution: AlmaLinux 10: OK"
      logInfo "Distribution: almalinux10, package manager: dnf"
      ;;
    "ol9"*)
      _PKG_COMMAND="dnf"
      _EL_MAJOR="9"
      consoleInfo "Distribution: Oracle Linux 9: OK"
      logInfo "Distribution: ol9, package manager: dnf"
      ;;
    "ol10"*)
      _PKG_COMMAND="dnf"
      _EL_MAJOR="10"
      consoleInfo "Distribution: Oracle Linux 10: OK"
      logInfo "Distribution: ol10, package manager: dnf"
      ;;
    "rhel9"*)
      _PKG_COMMAND="dnf"
      _EL_MAJOR="9"
      consoleInfo "Distribution: RHEL 9: OK"
      logInfo "Distribution: rhel9, package manager: dnf"
      ;;
    "rhel10"*)
      _PKG_COMMAND="dnf"
      _EL_MAJOR="10"
      consoleInfo "Distribution: RHEL 10: OK"
      logInfo "Distribution: rhel10, package manager: dnf"
      ;;
    *)
      consoleError "Unsupported distribution: ${PRETTY_NAME:-${distrib}}."
      consoleError "Supported: Debian 12, Debian 13, AlmaLinux 9/10, Oracle Linux 9/10, RHEL 9/10."
      logError "Unsupported distribution: ${distrib}"
      exit 1
      ;;
  esac
}

function _vmCheckSelinux() {
  if [ -z "${_EL_MAJOR}" ]; then
    return 0
  fi

  if ! command -v getenforce >/dev/null 2>&1; then
    consoleInfo "SELinux: getenforce not found, skipping check"
    logInfo "SELinux check skipped: getenforce not found"
    return 0
  fi

  local selinux_status
  selinux_status=$(getenforce)
  if [ "${selinux_status}" = "Enforcing" ]; then
    consoleError "SELinux is in Enforcing mode. Set it to Permissive or Disabled (setenforce 0, then update /etc/selinux/config) before installing the poller."
    logError "SELinux check failed: Enforcing"
    exit 1
  fi
  consoleInfo "SELinux: ${selinux_status}: OK"
  logInfo "SELinux check: ${selinux_status} OK"
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
