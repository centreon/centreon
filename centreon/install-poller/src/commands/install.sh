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
  _installParseArguments "$@"
  _installValidateArgs || exit 1
  _installDeriveCentral

  case "${POLLER_TYPE}" in
  docker)
    runDockerInstall
    ;;
  vm)
    runVmInstall
    ;;
  esac
}

function _installParseArguments() {
  while [ $# -gt 0 ]; do
    case $1 in
    --type)
      shift
      POLLER_TYPE=$1
      ;;
    --cloud)
      CLOUD_MODE="true"
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
      case "${CENTRAL_URL}" in
        https://*) CENTRAL_URL_SSL="true" ;;
        http://*) CENTRAL_URL_SSL="false" ;;
      esac
      local _url_no_scheme
      _url_no_scheme=$(echo "${CENTRAL_URL}" | sed 's|^https\?://||')
      CENTRAL_HOST=$(echo "${_url_no_scheme}" | cut -d: -f1 | cut -d/ -f1)
      # Explicit port only; the per-mode default is applied in _installDeriveCentral.
      CENTRAL_PORT=$(echo "${_url_no_scheme}" | cut -s -d: -f2 | cut -d/ -f1)
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
    --with-cma)
      WITH_CMA=1
      ;;
    *)
      consoleError "Unknown argument: '$1'. Run with --help to list valid flags."
      exit 1
      ;;
    esac
    shift
  done
}

function _installValidateArgs() {
  local ret=0

  if [ -z "${POLLER_TYPE}" ]; then
    consoleError "--type is required. Valid values: docker, vm."
    ret=1
  else
    case "${POLLER_TYPE}" in
    docker|vm) ;;
    *)
      consoleError "Invalid --type '${POLLER_TYPE}'. Valid values: docker, vm."
      ret=1
      ;;
    esac
  fi

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

  return ${ret}
}

# Single source of truth for the gorgone connection parameters,
# shared by both the docker and vm code paths.
# Gorgone's poller-facing websocket is fronted by Apache (mod_proxy_wstunnel) on
# the standard web port in both modes, not on a gorgone-specific port anymore.
#
# SSL is resolved in priority order: explicit --gorgone-ssl, then the
# http(s):// scheme in --central_url, then the --cloud default (Cloud is
# always HTTPS; on-prem defaults to the out-of-the-box plain-HTTP vhost
# until the admin configures SSL). The port then defaults to 443/80 to
# match whichever SSL value was resolved, unless explicitly set via
# --central_url's host:port.
function _installDeriveCentral() {
  if [ "${CLOUD_MODE}" = "true" ]; then
    GORGONE_ADDRESS="gorgone-centreon-${CENTRAL_HOST}"
    ENGINE_PORT="443"
  else
    GORGONE_ADDRESS="${CENTRAL_HOST}"
    ENGINE_PORT="5669"
  fi

  if [ -z "${GORGONE_SSL}" ]; then
    if [ -n "${CENTRAL_URL_SSL}" ]; then
      GORGONE_SSL="${CENTRAL_URL_SSL}"
    elif [ "${CLOUD_MODE}" = "true" ]; then
      GORGONE_SSL="true"
    else
      GORGONE_SSL="false"
    fi
  fi

  if [ -z "${CENTRAL_PORT}" ]; then
    if [ "${GORGONE_SSL}" = "true" ]; then
      CENTRAL_PORT="443"
    else
      CENTRAL_PORT="80"
    fi
  fi
}
