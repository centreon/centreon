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
      POLLER_TOKEN=$1
      ;;
    --uid)
      shift
      POLLER_UID=$1
      ;;
    --name)
      shift
      POLLER_NAME=$1
      ;;
    --central_url)
      shift
      CENTRAL_URL=$1
      ;;
    --appsecret)
      shift
      APP_SECRET=$1
      ;;
    --salt)
      shift
      SALT=$1
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
    if [ -z "${POLLER_TOKEN}" ]; then
      consoleError "--poller_token is required."
      ret=1
    fi
    if [ -z "${POLLER_UID}" ]; then
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
