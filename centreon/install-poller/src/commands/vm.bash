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

function runVmInstall() {
  echo ""
  consoleMainTitle "Installing Centreon poller"

  consoleTitle "Checking prerequisites:"
  _vmCheckRoot
  _vmDetectDistribution
  _vmCheckSelinux

  if _vmIsInstalled; then
    local installed_major
    installed_major=$(_vmGetInstalledMajor)

    echo ""
    consoleTitle "Existing installation detected (major: ${installed_major:-unknown}):"

    if [ -n "${installed_major}" ] && [ "${installed_major}" != "${major}" ]; then
      consoleInfo "Major version change: ${installed_major} → ${major}"
      _vmUpdateRepo
    else
      consoleInfo "Repository already at major ${major}, no repo update needed"
    fi

    echo ""
    consoleTitle "Updating Centreon packages:"
    _vmInstallPackages

    echo ""
    consoleTitle "Starting services:"
    _vmEnableServices
    _vmStartServices

    echo ""
    consoleInfo "Poller successfully updated."
  else
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
  fi
}
