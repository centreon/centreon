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
  echo "[ERROR] you must specify a major version for centreon as first argument"
  exit 1
fi

version=$2
if [ "${version}" = "" ]; then
  echo "[ERROR] you must specify a script version as second argument"
  exit 1
fi

if [ "${target}" = "" ]; then
  target="install-poller-${version}.sh"
  echo $target
fi

cp -f main-head.sh "${target}"

if [[ "$(uname)" == "Darwin" ]]; then
  gsed -i "s/major=\"\"/major=\"$major\"/g" "${target}"
  gsed -i "s/script version <VERSION>/script version ${version} /g" "${target}"
else
  sed -i "s/major=\"\"/major=\"$major\"/g" "${target}"
  sed -i "s/script version <VERSION>/script version ${version} /g" "${target}"
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
cat ./src/commands/vm.bash | tail -n +14 >> ${target}
cat ./src/commands/install.bash | tail -n +14 >> ${target}
cat main-tail.sh >> ${target}
