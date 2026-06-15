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

MAX_LOG_RETENTION=3

function initLog() {
  local filename=$1
  filename="${filename#"${filename%%[![:space:]]*}"}"
  filename="${filename%"${filename##*[![:space:]]}"}"
  if [ "${filename}" = "" ]; then
    echo -e "[$(errorColor ERROR)]The log filename is empty."
    exit 1
  fi

  local logdir=$(dirname "${filename}")
  if [ ! -d "${logdir}" ]; then
    echo -e "[$(errorColor ERROR)]The log directory doesn't exists."
    exit 1
  fi

  if [ -f "${filename}" ]; then
    rotateLog "${filename}"
  fi

  exec 7>"${filename}"

  exec 6>&2
  exec 2> >(stderrLog)
}

function closeLog() {
  exec 7>&-
  exec 2>&6 6>&-
}

function rotateLog() {
  local filename=$1

  for idx in $(seq $((${MAX_LOG_RETENTION}-1)) -1 1); do
    if [ -f "${filename}.${idx}" ]; then
      mv "${filename}.${idx}" "${filename}.$((${idx}+1))"
    fi
  done
  mv "${filename}" "${filename}.1"
}

function stderrLog() {
  while IFS= read -r msg; do
    logTrace "${msg}"
  done
}

function logError() {
  logMessage "ERROR" "$*"
}

function logWarn() {
  logMessage "WARN" "$*"
}

function logInfo() {
  logMessage "INFO" "$*"
}

function logDebug() {
  logMessage "DEBUG" "$*"
}

function logTrace() {
  logMessage "TRACE" "$*"
}

function logMessage() {
  local level=$1
  shift
  printf "%s - %-5s - " "$(date -u +"%Y-%m-%dT%H:%M:%SZ")" "${level}" >&7
  echo "$*" >&7
}
