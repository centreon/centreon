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

script_dir="$(dirname "$0")"

# MAJOR (e.g. 26.07) — used for image tags and repository URLs at runtime.
major=$1
if [ "${major}" = "" ]; then
  version_file="${script_dir}/../../.version"
  if [ -f "${version_file}" ]; then
    major=$(grep "^MAJOR=" "${version_file}" | cut -d= -f2)
  fi
  if [ "${major}" = "" ]; then
    echo "[ERROR] you must specify a major version as first argument or have a .version file at repo root"
    exit 1
  fi
  echo "[INFO] using major version from .version: ${major}"
fi

# MINOR (e.g. 0) — only used to stamp the full version label in the script.
minor=$2
if [ "${minor}" = "" ]; then
  minor_file="${script_dir}/../../.version.centreon-web"
  if [ -f "${minor_file}" ]; then
    minor=$(grep "^MINOR=" "${minor_file}" | cut -d= -f2)
    echo "[INFO] using minor version from .version.centreon-web: ${minor}"
  fi
fi

# Full version label: MAJOR.MINOR (e.g. 26.07.0), falling back to MAJOR alone.
full_version="${major}"
if [ "${minor}" != "" ]; then
  full_version="${major}.${minor}"
fi

if [ "${target}" = "" ]; then
  target="install.sh"
fi

cp -f main-head.sh "${target}"

if [[ "$(uname)" == "Darwin" ]]; then
  sed -i '' "s/major=\"\"/major=\"$major\"/g" "${target}"
  sed -i '' "s/<VERSION>/${full_version}/g" "${target}"
else
  sed -i "s/major=\"\"/major=\"$major\"/g" "${target}"
  sed -i "s/<VERSION>/${full_version}/g" "${target}"
fi

# Strip the leading license header (lines 1 through the first blank line) from a
# source file and append the remainder to the target. Robust to header length
# changes, unlike a fixed 'tail -n +N'.
append_without_header() {
  sed '1,/^$/d' "$1" >> "${target}"
}

## Copying lib files
append_without_header ./lib/term.sh
append_without_header ./lib/color.sh
append_without_header ./lib/console.sh
append_without_header ./lib/log.sh
append_without_header ./lib/command.sh

## Copying src files
append_without_header ./src/cmdparse.sh
append_without_header ./src/help.sh
append_without_header ./src/commands/docker.sh
## VM submodules (loaded before the orchestrator)
append_without_header ./src/vm/prerequisites.sh
append_without_header ./src/vm/packages.sh
append_without_header ./src/vm/configure.sh
append_without_header ./src/vm/services.sh
append_without_header ./src/commands/vm.sh
append_without_header ./src/commands/install.sh
cat main-tail.sh >> ${target}
