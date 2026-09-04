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

# The major to resolve VM package repo URLs against. For the stable channel, a
# cloud major resolves to the previous on-prem major (same as
# _pollerImageMajor for the Docker image tag, src/commands/docker.sh) so a
# real customer install never needs internal repo access — RPM/DEB packages
# for a cloud major are only ever published internally. testing/unstable stay
# pinned to the actual major being validated: QA needs the real cloud
# candidate build, not an older on-prem stand-in. MON-208333.
function _vmRepoMajor() {
  if [ "${STABILITY}" = "stable" ]; then
    _pollerImageMajor
  else
    echo "${major}"
  fi
}

# Mirrors uses_internal_repo() in centreon/unattended.sh.
function _usesInternalRepo() {
  ! _isOnPremMajor "$(_vmRepoMajor)"
}

function _centreonRpmRepoUrl() {
  local repo_major
  repo_major=$(_vmRepoMajor)
  if _usesInternalRepo; then
    echo "https://packages.centreon.com/rpm-standard-internal/${repo_major}/el${_EL_MAJOR:-9}/centreon-${repo_major}-internal.repo"
  else
    echo "https://packages.centreon.com/rpm-standard/${repo_major}/el${_EL_MAJOR:-9}/centreon-${repo_major}.repo"
  fi
}

# Safe to call repeatedly (e.g. re-run with an install.sh built for a
# different stability).
function _vmConfigureRepoChannels() {
  consoleInfo "Configuring Centreon repository channel for stability: ${STABILITY}"
  logInfo "Configuring repo channel for stability=${STABILITY}"

  if [ "${_PKG_COMMAND}" = "dnf" ]; then
    commandExitOnError "Cannot enable Centreon repositories" \
      dnf config-manager --set-enabled 'centreon*'

    case "${STABILITY}" in
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
    local repo_major
    repo_major=$(_vmRepoMajor)
    local apt_root="apt-standard"
    _usesInternalRepo && apt_root="apt-standard-internal"

    rm -f /etc/apt/sources.list.d/centreon-stable.list \
          /etc/apt/sources.list.d/centreon-testing.list \
          /etc/apt/sources.list.d/centreon-unstable.list

    echo "deb https://packages.centreon.com/${apt_root}/ ${codename}-${repo_major}-stable main" \
      | tee /etc/apt/sources.list.d/centreon-stable.list > /dev/null

    if [ "${STABILITY}" = "testing" ] || [ "${STABILITY}" = "unstable" ]; then
      {
        echo "deb https://packages.centreon.com/${apt_root}/ ${codename}-${repo_major}-testing-hotfix main"
        echo "deb https://packages.centreon.com/${apt_root}/ ${codename}-${repo_major}-testing-release main"
      } | tee /etc/apt/sources.list.d/centreon-testing.list > /dev/null
    fi

    if [ "${STABILITY}" = "unstable" ]; then
      echo "deb https://packages.centreon.com/${apt_root}/ ${codename}-${repo_major}-unstable main" \
        | tee /etc/apt/sources.list.d/centreon-unstable.list > /dev/null
    fi

    commandExitOnError "Cannot update apt repositories" apt update
  fi
}

function _vmInstallRepo() {
  logInfo "Adding Centreon $(_vmRepoMajor) repository (stability: ${STABILITY})"

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
  local repo_major
  repo_major=$(_vmRepoMajor)
  consoleInfo "Updating Centreon repository to ${repo_major} (stability: ${STABILITY})"
  logInfo "Updating Centreon repository to ${repo_major}, stability=${STABILITY}"

  if [ "${_PKG_COMMAND}" = "dnf" ]; then
    rm -f /etc/yum.repos.d/centreon-*.repo
    commandExitOnError "Cannot add Centreon ${repo_major} repository" \
      dnf config-manager --add-repo "$(_centreonRpmRepoUrl)"
    _vmConfigureRepoChannels
    dnf clean all
  else
    _vmConfigureRepoChannels
  fi
}
