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

function _vmIsInstalled() {
  if [ "${_PKG_COMMAND}" = "dnf" ]; then
    rpm -q centreon-poller &>/dev/null
  else
    dpkg -l centreon-poller 2>/dev/null | grep -q '^ii'
  fi
}

function _vmGetInstalledMajor() {
  local pkg_version
  if [ "${_PKG_COMMAND}" = "dnf" ]; then
    pkg_version=$(rpm -q --queryformat '%{VERSION}' centreon-poller 2>/dev/null)
  else
    pkg_version=$(dpkg-query -W -f='${Version}' centreon-poller 2>/dev/null)
  fi
  echo "${pkg_version}" | grep -oE '^[0-9]+\.[0-9]+'
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
      ;;
    "rhel9"*)
      commandExitOnError "Cannot install dnf-plugins-core" \
        dnf -y install dnf-plugins-core
      commandExitOnError "Cannot install EPEL for RHEL 9" \
        dnf -y install https://dl.fedoraproject.org/pub/epel/epel-release-latest-9.noarch.rpm
      commandExitOnError "Cannot enable RHEL 9 CRB" \
        subscription-manager repos --enable codeready-builder-for-rhel-9-x86_64-rpms
      ;;
    "almalinux10"*)
      # TODO: double check prerequisite for 26.10 release
      commandExitOnError "Cannot install dnf-plugins-core and epel-release" \
        dnf -y install dnf-plugins-core epel-release
      commandExitOnError "Cannot enable CRB" \
        crb enable
      ;;
    "ol10"*)
      # TODO: double check prerequisite for 26.10 release
      commandExitOnError "Cannot install dnf-plugins-core" \
        dnf -y install dnf-plugins-core
      commandExitOnError "Cannot install EPEL for Oracle Linux 10" \
        dnf -y install https://dl.fedoraproject.org/pub/epel/epel-release-latest-10.noarch.rpm
      commandExitOnError "Cannot enable OL10 CodeReady Builder" \
        dnf config-manager --set-enabled ol10_codeready_builder
      ;;
    "rhel10"*)
      # TODO: double check prerequisite for 26.10 release
      commandExitOnError "Cannot install dnf-plugins-core" \
        dnf -y install dnf-plugins-core
      commandExitOnError "Cannot install EPEL for RHEL 10" \
        dnf -y install https://dl.fedoraproject.org/pub/epel/epel-release-latest-10.noarch.rpm
      commandExitOnError "Cannot enable RHEL 10 CRB" \
        subscription-manager repos --enable codeready-builder-for-rhel-10-x86_64-rpms
      ;;
  esac
}

# Mirrors uses_internal_repo() in centreon/unattended.sh.
function _usesInternalRepo() {
  [ "${major##*.}" != "10" ]
}

function _centreonRpmRepoUrl() {
  if _usesInternalRepo; then
    echo "https://packages.centreon.com/rpm-standard-internal/${major}/el${_EL_MAJOR:-9}/centreon-${major}-internal.repo"
  else
    echo "https://packages.centreon.com/rpm-standard/${major}/el${_EL_MAJOR:-9}/centreon-${major}.repo"
  fi
}

# Safe to call repeatedly (e.g. re-run with an install.sh built for a
# different stability). FORCE_STABILITY (hidden --stability flag) overrides
# STABILITY here. MON-208554.
function _vmConfigureRepoChannels() {
  local effective_stability="${FORCE_STABILITY:-${STABILITY}}"
  consoleInfo "Configuring Centreon repository channel for stability: ${effective_stability}"
  logInfo "Configuring repo channel for stability=${effective_stability}"

  if [ "${_PKG_COMMAND}" = "dnf" ]; then
    commandExitOnError "Cannot enable Centreon repositories" \
      dnf config-manager --set-enabled 'centreon*'

    case "${effective_stability}" in
    testing-release)
      # Isolate from testing-hotfix (MON-208554): the two are separate repos
      # that can disagree on version, so leaving both enabled makes dnf's
      # pick ambiguous. Only meaningful via the --stability override; the
      # unforced 'testing' case below keeps the prior (both enabled) default.
      commandExitOnError "Cannot disable unstable/testing-hotfix repositories" \
        dnf config-manager --set-disabled 'centreon*unstable*' --set-disabled 'centreon*testing-hotfix*'
      ;;
    testing-hotfix)
      commandExitOnError "Cannot disable unstable/testing-release repositories" \
        dnf config-manager --set-disabled 'centreon*unstable*' --set-disabled 'centreon*testing-release*'
      ;;
    testing)
      commandExitOnError "Cannot disable unstable repository" \
        dnf config-manager --set-disabled 'centreon*unstable*'
      ;;
    stable)
      commandExitOnError "Cannot disable unstable/testing repositories" \
        dnf config-manager --set-disabled 'centreon*unstable*' --set-disabled 'centreon*testing*'
      ;;
    esac
    # unstable: everything from --set-enabled 'centreon*' stays enabled.

    # Plugins channel always pinned to stable.
    commandExitOnError "Cannot pin plugins repository to stable" \
      dnf config-manager --set-disabled 'centreon-plugin*unstable*' --set-disabled 'centreon-plugin*testing*'

  else
    local codename
    codename=$(lsb_release -sc)
    local apt_root="apt-standard"
    _usesInternalRepo && apt_root="apt-standard-internal"

    rm -f /etc/apt/sources.list.d/centreon-stable.list \
          /etc/apt/sources.list.d/centreon-testing.list \
          /etc/apt/sources.list.d/centreon-unstable.list

    echo "deb https://packages.centreon.com/${apt_root}/ ${codename}-${major}-stable main" \
      | tee /etc/apt/sources.list.d/centreon-stable.list > /dev/null

    case "${effective_stability}" in
    testing-release)
      # Isolate from testing-hotfix (MON-208554), same rationale as the dnf case above.
      echo "deb https://packages.centreon.com/${apt_root}/ ${codename}-${major}-testing-release main" \
        | tee /etc/apt/sources.list.d/centreon-testing.list > /dev/null
      ;;
    testing-hotfix)
      echo "deb https://packages.centreon.com/${apt_root}/ ${codename}-${major}-testing-hotfix main" \
        | tee /etc/apt/sources.list.d/centreon-testing.list > /dev/null
      ;;
    testing | unstable)
      {
        echo "deb https://packages.centreon.com/${apt_root}/ ${codename}-${major}-testing-hotfix main"
        echo "deb https://packages.centreon.com/${apt_root}/ ${codename}-${major}-testing-release main"
      } | tee /etc/apt/sources.list.d/centreon-testing.list > /dev/null
      ;;
    esac

    if [ "${effective_stability}" = "unstable" ]; then
      echo "deb https://packages.centreon.com/${apt_root}/ ${codename}-${major}-unstable main" \
        | tee /etc/apt/sources.list.d/centreon-unstable.list > /dev/null
    fi

    commandExitOnError "Cannot update apt repositories" apt update
  fi
}

function _vmInstallRepo() {
  logInfo "Adding Centreon ${major} repository (stability: ${FORCE_STABILITY:-${STABILITY}})"

  if [ "${_PKG_COMMAND}" = "apt" ]; then
    consoleInfo "Adding Centreon repository (Debian)"
    commandExitOnError "Cannot update apt" \
      apt update
    commandExitOnError "Cannot install repo prerequisites" \
      env DEBIAN_FRONTEND=noninteractive apt install -y lsb-release ca-certificates wget gnupg2

    consoleInfo "Importing Centreon GPG key"
    commandExitOnError "Cannot import Centreon GPG key" \
      bash -c 'wget -O- https://apt-key.centreon.com | gpg --dearmor | tee /etc/apt/trusted.gpg.d/centreon.gpg > /dev/null 2>&1'

    echo "deb https://packages.centreon.com/apt-plugins-stable/ $(lsb_release -sc) main" \
      | tee /etc/apt/sources.list.d/centreon-plugins.list > /dev/null

    _vmConfigureRepoChannels

  elif [ "${_PKG_COMMAND}" = "dnf" ]; then
    consoleInfo "Adding Centreon repository (RPM/EL${_EL_MAJOR:-9})"
    commandExitOnError "Cannot add Centreon repository" \
      dnf config-manager --add-repo "$(_centreonRpmRepoUrl)"

    _vmConfigureRepoChannels
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

function _vmUpdateRepo() {
  local effective_stability="${FORCE_STABILITY:-${STABILITY}}"
  consoleInfo "Updating Centreon repository to ${major} (stability: ${effective_stability})"
  logInfo "Updating Centreon repository to ${major}, stability=${effective_stability}"

  if [ "${_PKG_COMMAND}" = "dnf" ]; then
    rm -f /etc/yum.repos.d/centreon-*.repo
    commandExitOnError "Cannot add Centreon ${major} repository" \
      dnf config-manager --add-repo "$(_centreonRpmRepoUrl)"
    _vmConfigureRepoChannels
    dnf clean all
  else
    _vmConfigureRepoChannels
  fi
}
