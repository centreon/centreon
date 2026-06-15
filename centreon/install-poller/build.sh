#!/bin/bash
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

major=$1
if [ "${major}" = "" ]; then
  version_file="$(dirname "$0")/../../.version"
  if [ -f "${version_file}" ]; then
    major=$(grep "^MAJOR=" "${version_file}" | cut -d= -f2)
  fi
  if [ "${major}" = "" ]; then
    echo "[ERROR] you must specify a major version as first argument or have a .version file at repo root"
    exit 1
  fi
  echo "[INFO] using major version from .version: ${major}"
fi

if [ "${target}" = "" ]; then
  target="install.sh"
fi

cp -f main-head.sh "${target}"

if [[ "$(uname)" == "Darwin" ]]; then
  gsed -i "s/major=\"\"/major=\"$major\"/g" "${target}"
  gsed -i "s/<MAJOR>/${major}/g" "${target}"
else
  sed -i "s/major=\"\"/major=\"$major\"/g" "${target}"
  sed -i "s/<MAJOR>/${major}/g" "${target}"
fi

# Strip the leading license header (lines 1 through the first blank line) from a
# source file and append the remainder to the target. Robust to header length
# changes, unlike a fixed 'tail -n +N'.
append_without_header() {
  sed '1,/^$/d' "$1" >> "${target}"
}

## Copying lib files
append_without_header ./lib/term.bash
append_without_header ./lib/color.bash
append_without_header ./lib/console.bash
append_without_header ./lib/log.bash
append_without_header ./lib/command.bash

## Copying src files
append_without_header ./src/cmdparse.bash
append_without_header ./src/help.bash
append_without_header ./src/commands/docker.bash
## VM submodules (loaded before the orchestrator)
append_without_header ./src/vm/prerequisites.bash
append_without_header ./src/vm/packages.bash
append_without_header ./src/vm/configure.bash
append_without_header ./src/vm/services.bash
append_without_header ./src/commands/vm.bash
append_without_header ./src/commands/install.bash
cat main-tail.sh >> ${target}
