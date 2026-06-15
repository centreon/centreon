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
    "almalinux10"*)
      # TODO: double check prerequisite for 26.10 release
      commandExitOnError "Cannot install dnf-plugins-core and epel-release" \
        dnf -y install dnf-plugins-core epel-release
      commandExitOnError "Cannot enable CRB" \
        dnf config-manager --set-enabled crb
      ;;
    "ol10"*)
      # TODO: double check prerequisite for 26.10 release
      commandExitOnError "Cannot install dnf-plugins-core" \
        dnf -y install dnf-plugins-core
      commandExitOnError "Cannot install EPEL for Oracle Linux 10" \
        dnf -y install https://dl.fedoraproject.org/pub/epel/epel-release-latest-10.noarch.rpm
      commandExitOnError "Cannot enable OL10 CodeReady Builder" \
        dnf config-manager --set-enabled ol10_codeready_builder
      commandExitOnError "Cannot install epel-release" \
        dnf -y install epel-release
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
    consoleInfo "Adding Centreon repository (RPM/EL${_EL_MAJOR:-9})"
    commandExitOnError "Cannot add Centreon repository" \
      dnf config-manager --add-repo "https://packages.centreon.com/rpm-standard/${major}/el${_EL_MAJOR:-9}/centreon-${major}.repo"
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
  consoleInfo "Updating Centreon repository to ${major}"
  logInfo "Updating Centreon repository to ${major}"

  if [ "${_PKG_COMMAND}" = "dnf" ]; then
    rm -f /etc/yum.repos.d/centreon-*.repo
    commandExitOnError "Cannot add Centreon ${major} repository" \
      dnf config-manager --add-repo \
        "https://packages.centreon.com/rpm-standard/${major}/el${_EL_MAJOR:-9}/centreon-${major}.repo"
    dnf clean all
  else
    local codename
    codename=$(lsb_release -sc)
    echo "deb https://packages.centreon.com/apt-standard/ ${codename}-${major}-stable main" \
      | tee /etc/apt/sources.list.d/centreon-stable.list > /dev/null
    commandExitOnError "Cannot update apt repositories" apt update
  fi
}
