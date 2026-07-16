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

PROG_NAME="install-poller"

function help() {
  local subcommand=${1:-"[command]"}

  echo ""
  echo "The ${PROG_NAME} installs and configures a Centreon poller." | fold -s -w ${MAX_CHARACTERS}
  echo ""
  echo "Usage:"
  echo "  ${PROG_NAME} ${subcommand}"
  echo ""

  case ${subcommand} in
  install)
    echo "Flags:"
    echo -e "  --cloud\t\t\tEnable Cloud mode (default: on-prem)"
    echo ""
    echo "Required flags (all types):"
    echo -e "  --type string\t\t\tDeployment type: docker | vm"
    echo -e "  --poller_token string\t\tGorgone poller token"
    echo -e "  --uid string\t\t\tPoller unique ID"
    echo -e "  --name string\t\t\tPoller name"
    echo -e "  --central_url string\t\tCentral URL (cloud: avvcentreon.euwest1.centreon.cloud | on-prem: [http(s)://]host[:port])"
    echo -e "  --appsecret string\t\tApplication secret"
    echo -e "  --salt string\t\t\tEncryption salt"
    echo ""
    echo "Docker behavior flags:"
    echo -e "  --no-start\t\t\tOnly generate files, do not run docker compose up -d"
    echo ""
    echo "Docker optional flags:"
    echo -e "  --tz string\t\t\tTimezone (default: UTC)"
    echo -e "  --debug bool\t\t\tEnable debug logging (default: false)"
    echo -e "  --gorgone-ssl bool\t\tEnable SSL for Gorgone (overrides the http(s):// scheme in --central_url; default depends on --cloud)"
    echo -e "  --with-vmware\t\t\tInclude centreon-vmware service"
    echo -e "  --with-snmptrap\t\tInclude snmptrapd and centreontrapd services"
    echo -e "  --with-cma\t\t\tEnable Centreon Monitoring Agent support (TLS certs mounts + port 4317)"
    echo ""
    echo "Notes:"
    echo -e "  --cloud\t\t\tGorgone address auto-derived: gorgone-centreon-<central_url>, port 443, ssl=true"
    echo -e "  (no --cloud)\t\t\tOn-prem: Gorgone address from --central_url; ssl/port default to false/80, or true/443 if --central_url starts with https://, or --gorgone-ssl if set"
    ;;
  *)
    echo "Available commands:"
    echo "  install  Install and configure the poller"
    ;;
  esac
  echo ""
  echo "Global flags:"
  echo -e "  -h, --help\tDisplay the help of the command"
}
