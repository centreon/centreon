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
    if [ "${CHANNEL}" = "broker" ]; then
      runDockerBrokerInstall
    else
      runDockerInstall
    fi
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
      _url_no_scheme=$(echo "${CENTRAL_URL}" | sed -E 's|^https?://||')
      CENTRAL_HOST=$(echo "${_url_no_scheme}" | cut -d: -f1 | cut -d/ -f1)
      # Explicit port only; the per-mode default is applied in _installDeriveCentral.
      CENTRAL_PORT=$(echo "${_url_no_scheme}" | cut -s -d: -f2 | cut -d/ -f1)
      # Path segment of --central_url (e.g. "/centreon"), if any. The
      # generated install one-liner always includes the real base_uri here,
      # so an absent path legitimately means the app is mounted at "/".
      CENTRAL_BASE_URI=$(echo "${_url_no_scheme}" | grep -o '/.*' || true)
      CENTRAL_BASE_URI="${CENTRAL_BASE_URI%/}"
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
    --overwrite)
      OVERWRITE=1
      ;;
    --with-vmware)
      WITH_VMWARE=1
      ;;
    --vmware-path)
      shift
      if [ $# -eq 0 ] || [ "${1#--}" != "$1" ]; then
        consoleError "--vmware-path requires a path."
        exit 1
      fi
      VMWARE_PATH=$1
      ;;
    --with-snmptrap)
      WITH_SNMPTRAP=1
      ;;
    --with-cma)
      WITH_CMA=1
      ;;
    --cma-hostname)
      shift
      if [ $# -eq 0 ] || [ "${1#--}" != "$1" ]; then
        consoleError "--cma-hostname requires a hostname."
        exit 1
      fi
      CMA_HOSTNAME=$1
      ;;
    --channel)
      shift
      CHANNEL=$1
      ;;
    --poller_id)
      shift
      POLLER_ID=$1
      ;;
    --broker_host)
      shift
      BROKER_HOST=$1
      ;;
    --broker_port)
      shift
      BROKER_PORT=$1
      ;;
    --broker_tls_hostname)
      shift
      BROKER_TLS_HOSTNAME=$1
      ;;
    --broker_ca_sha256)
      shift
      BROKER_CA_SHA256=$1
      ;;
    --docker_network)
      shift
      DOCKER_NETWORK=$1
      ;;
    --add_host)
      shift
      ADD_HOSTS+=("$1")
      ;;
    --registry)
      shift
      FORCE_REGISTRY=$1
      ;;
    --tag)
      shift
      FORCE_TAG=$1
      ;;
    --stability)
      shift
      FORCE_STABILITY=$1
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

  if [ -n "${FORCE_REGISTRY}" ]; then
    case "${FORCE_REGISTRY}" in
    harbor | ghcr) ;;
    *)
      # A full image prefix (registry host + path), e.g. registry.example.com/centreon:
      # images become <prefix>/centreon-<component>-trixie (see _pollerImageRepo).
      if ! [[ "${FORCE_REGISTRY}" =~ ^[A-Za-z0-9][A-Za-z0-9._:-]*(/[A-Za-z0-9._-]+)*/?$ ]] ||
        ! [[ "${FORCE_REGISTRY}" == */* || "${FORCE_REGISTRY}" == *.* ]]; then
        consoleError "Invalid --registry '${FORCE_REGISTRY}'. Valid values: harbor, ghcr, or an image prefix such as registry.example.com/centreon."
        ret=1
      fi
      ;;
    esac
  fi

  if [ -n "${DOCKER_NETWORK}" ] && ! [[ "${DOCKER_NETWORK}" =~ ^[A-Za-z0-9][A-Za-z0-9_.-]*$ ]]; then
    consoleError "Invalid --docker_network '${DOCKER_NETWORK}'. Expected an existing Docker network name."
    ret=1
  fi

  local add_host
  for add_host in "${ADD_HOSTS[@]+"${ADD_HOSTS[@]}"}"; do
    if ! _isValidAddHost "${add_host}"; then
      consoleError "Invalid --add_host '${add_host}'. Expected <hostname>:<IPv4|IPv6|host-gateway>."
      ret=1
    fi
  done

  case "${CHANNEL}" in
  gorgone)
    if [ -n "${DOCKER_NETWORK}" ] || [ ${#ADD_HOSTS[@]} -gt 0 ]; then
      consoleError "--docker_network and --add_host are only available with --channel broker."
      ret=1
    fi
    ;;
  broker)
    _installValidateBrokerArgs || ret=1
    ;;
  *)
    consoleError "Invalid --channel '${CHANNEL}'. Valid values: gorgone, broker."
    ret=1
    ;;
  esac

  if [ -n "${FORCE_STABILITY}" ]; then
    case "${FORCE_STABILITY}" in
    stable | testing-release | testing-hotfix | unstable) ;;
    *)
      consoleError "Invalid --stability '${FORCE_STABILITY}'. Valid values: stable, testing-release, testing-hotfix, unstable."
      ret=1
      ;;
    esac
  fi

  # Becomes the centengine container hostname: RFC 1123, max 64 chars (kernel limit)
  if [ -n "${CMA_HOSTNAME}" ] && [ "${POLLER_TYPE}" = "docker" ]; then
    local label='[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?'
    if [ "${#CMA_HOSTNAME}" -gt 64 ] || ! [[ "${CMA_HOSTNAME}" =~ ^${label}(\.${label})*$ ]]; then
      consoleError "Invalid --cma-hostname '${CMA_HOSTNAME}'. Expected a hostname or FQDN (e.g. poller.example.com), max 64 characters, no scheme or port."
      ret=1
    fi
  fi

  return ${ret}
}

# Single source of truth for the gorgone connection parameters,
# shared by both the docker and vm code paths.
# Gorgone's poller-facing websocket is fronted by Apache (mod_proxy_wstunnel) on
# the standard web port in both modes, not on a gorgone-specific port or
# dedicated "gorgone-" endpoint anymore — cloud and on-prem both reach it
# through the same central host, just at ${GORGONE_PULLWSS_CENTRAL_URI}.
#
# SSL is resolved in priority order: explicit --gorgone-ssl, then the
# http(s):// scheme in --central_url, then the --cloud default (Cloud is
# always HTTPS; on-prem defaults to the out-of-the-box plain-HTTP vhost
# until the admin configures SSL). The port then defaults to 443/80 to
# match whichever SSL value was resolved, unless explicitly set via
# --central_url's host:port.
function _installDeriveCentral() {
  GORGONE_ADDRESS="${CENTRAL_HOST}"

  if [ "${CLOUD_MODE}" = "true" ]; then
    ENGINE_PORT="443"
  else
    ENGINE_PORT="5669"
  fi

  GORGONE_PULLWSS_CENTRAL_URI="${CENTRAL_BASE_URI}/gorgone/pullwss/websocket"

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

# --add_host <hostname>:<IPv4|IPv6|host-gateway>, becomes a compose extra_hosts entry.
function _isValidAddHost() {
  local value=$1
  local name="${value%%:*}"
  local target="${value#*:}"
  local label='[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?'

  [ "${name}" != "${value}" ] || return 1
  [[ "${name}" =~ ^${label}(\.${label})*$ ]] || return 1
  [ "${target}" = "host-gateway" ] && return 0
  [[ "${target}" =~ ^[0-9]{1,3}(\.[0-9]{1,3}){3}$ ]] && return 0
  [[ "${target}" =~ ^[0-9A-Fa-f:]+$ && "${target}" == *:* ]] && return 0
  return 1
}

# MON-215450 phase 2g (contracts.md § 11.W): Docker poller reached through the
# central broker. Everything is checked before any file is written. The values
# below end up in .env (single-quoted, read by compose) and, through the engine
# bootstrap, in JSON files and a file name: refuse what would break either.
function _installValidateBrokerArgs() {
  local ret=0
  local host_re='^[A-Za-z0-9._:-]{1,253}$'

  if [ "${POLLER_TYPE}" != "docker" ]; then
    consoleError "--channel broker requires --type docker."
    ret=1
  fi
  if [ "${CLOUD_MODE}" = "true" ]; then
    consoleError "--channel broker does not support --cloud."
    ret=1
  fi
  if [ "${WITH_SNMPTRAP}" = "1" ] || [ "${WITH_VMWARE}" = "1" ]; then
    consoleError "--with-snmptrap and --with-vmware need gorgone: not available with --channel broker."
    ret=1
  fi

  if ! [[ "${GORGONE_UID}" =~ ^[1-9][0-9]{0,18}$ ]]; then
    consoleError "--uid must be a positive integer."
    ret=1
  fi
  if ! [[ "${POLLER_ID}" =~ ^[1-9][0-9]{0,9}$ ]]; then
    consoleError "--poller_id is required with --channel broker (positive integer)."
    ret=1
  fi
  if ! [[ "${BROKER_HOST}" =~ ${host_re} ]]; then
    consoleError "--broker_host is required with --channel broker (hostname or IP address the poller dials)."
    ret=1
  fi
  BROKER_PORT="${BROKER_PORT:-5669}"
  if ! [[ "${BROKER_PORT}" =~ ^[0-9]{1,5}$ ]] || [ "${BROKER_PORT}" -lt 1 ] || [ "${BROKER_PORT}" -gt 65535 ]; then
    consoleError "Invalid --broker_port '${BROKER_PORT}'."
    ret=1
  fi
  if [ -n "${BROKER_TLS_HOSTNAME}" ] && ! [[ "${BROKER_TLS_HOSTNAME}" =~ ${host_re} ]]; then
    consoleError "Invalid --broker_tls_hostname '${BROKER_TLS_HOSTNAME}'."
    ret=1
  fi

  BROKER_CA_SHA256=$(printf '%s' "${BROKER_CA_SHA256}" | tr -d ':' | tr '[:upper:]' '[:lower:]')
  if [ -z "${BROKER_CA_SHA256}" ]; then
    consoleError "--broker_ca_sha256 is missing: the central broker CA was not available when the installation command was generated."
    consoleError "Regenerate the installation command once the central broker is running, then run it again."
    ret=1
  elif ! [[ "${BROKER_CA_SHA256}" =~ ^[0-9a-f]{64}$ ]]; then
    consoleError "Invalid --broker_ca_sha256: expected the 64 hex characters of a SHA-256."
    ret=1
  fi

  if ! _isSafeEnvValue "${APP_SECRET}" || ! _isSafeEnvValue "${SALT}"; then
    consoleError "--appsecret and --salt must not contain quotes, backslashes, '\$' or line breaks."
    ret=1
  fi
  if ! _isSafeEnvValue "${POLLER_NAME}" || [[ "${POLLER_NAME}" == */* ]]; then
    consoleError "--name must not contain quotes, backslashes, '\$', '/' or line breaks with --channel broker."
    ret=1
  fi

  return ${ret}
}

function _isSafeEnvValue() {
  local value=$1
  local forbidden=$'[\'"\\$\n\r]'
  ! [[ "${value}" =~ ${forbidden} ]]
}
