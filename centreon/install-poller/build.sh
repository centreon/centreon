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

## N.B the 'tail -n +14' is used to skip the first 13 lines of each file which are the license header
## Copying lib files
cat ./lib/term.bash | tail -n +14 >> ${target}
cat ./lib/color.bash | tail -n +14 >> ${target}
cat ./lib/console.bash | tail -n +14 >> ${target}
cat ./lib/log.bash | tail -n +14 >> ${target}
cat ./lib/command.bash | tail -n +14 >> ${target}

## Copying src files
cat ./src/cmdparse.bash | tail -n +14 >> ${target}
cat ./src/help.bash | tail -n +14 >> ${target}
cat ./src/commands/docker.bash | tail -n +14 >> ${target}
## VM submodules (loaded before the orchestrator)
cat ./src/vm/prerequisites.bash | tail -n +14 >> ${target}
cat ./src/vm/packages.bash | tail -n +14 >> ${target}
cat ./src/vm/configure.bash | tail -n +14 >> ${target}
cat ./src/vm/services.bash | tail -n +14 >> ${target}
cat ./src/commands/vm.bash | tail -n +14 >> ${target}
cat ./src/commands/install.bash | tail -n +14 >> ${target}
cat main-tail.sh >> ${target}
