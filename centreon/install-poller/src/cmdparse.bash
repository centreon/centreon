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

function cmdParse() {
  local subcommand=""
  if [ "${1:-}" = "install" ]; then
    subcommand="install"
    shift
  elif [ $# -gt 0 ] && ! helpFlag "$@"; then
    # The generated installation command (API) omits the "install" keyword
    # and passes flags directly, e.g. `install.sh --type docker ...`.
    subcommand="install"
  fi

  if helpFlag "$@"; then
    help "${subcommand}"
    return
  fi

  case "${subcommand}" in
  install)
    installCommand "$@"
    ;;
  *)
    help
    ;;
  esac
}

function helpFlag() {
  for arg in "$@"; do
    if [ "${arg}" = "-h" ] || [ "${arg}" = "--help" ]; then
      return 0
    fi
  done

  return 1
}
