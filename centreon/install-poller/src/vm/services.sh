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
