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

function _vmConfigureGorgone() {
  consoleInfo "Writing gorgone configuration"
  logInfo "Configuring /etc/centreon-gorgone/config.d/40-gorgoned.yaml"

  # Connection parameters are derived once in _installDeriveCentral.
  local central_host="${GORGONE_ADDRESS}"
  local central_port="${CENTRAL_PORT}"
  local ssl_mode="${GORGONE_SSL}"

  mkdir -p /etc/centreon-gorgone/config.d
  local old_umask
  old_umask="$(umask)"
  umask 027
  cat > /etc/centreon-gorgone/config.d/40-gorgoned.yaml <<EOF
name: ${POLLER_NAME}
description: Poller configuration
gorgone:
  gorgonecore:
    id: ${GORGONE_UID}
    privkey: /var/lib/centreon-gorgone/.keys/rsakey.priv.pem
    pubkey: /var/lib/centreon-gorgone/.keys/rsakey.pub.pem

  modules:
    - name: engine
      package: gorgone::modules::centreon::engine::hooks
      enable: true
      command_file: "/var/lib/centreon-engine/rw/centengine.cmd"

    - name: pullwss
      package: "gorgone::modules::core::pullwss::hooks"
      enable: true
      ssl: ${ssl_mode}
      port: ${central_port}
      token: ${GORGONE_TOKEN}
      address: ${central_host}
EOF
  local ret=$?
  umask "${old_umask}"

  if [ ${ret} -ne 0 ]; then
    consoleError "Cannot write gorgone configuration."
    logError "Cannot write /etc/centreon-gorgone/config.d/40-gorgoned.yaml"
    exit 1
  fi

  chown centreon-gorgone:centreon-gorgone /etc/centreon-gorgone/config.d/40-gorgoned.yaml
  consoleInfo "Gorgone configuration written"
  logInfo "Gorgone configuration written (address: ${central_host}:${central_port}, ssl: ${ssl_mode})"
}

function _vmConfigureEngineContext() {
  consoleInfo "Writing engine context"
  logInfo "Configuring /etc/centreon-engine/engine-context.json"

  mkdir -p /etc/centreon-engine
  local old_umask
  old_umask="$(umask)"
  umask 006
  printf '{"app_secret":"%s","salt":"%s"}' "${APP_SECRET}" "${SALT}" \
    > /etc/centreon-engine/engine-context.json
  local ret=$?
  umask "${old_umask}"

  if [ ${ret} -ne 0 ]; then
    consoleError "Cannot write engine context."
    logError "Cannot write /etc/centreon-engine/engine-context.json"
    exit 1
  fi

  chown root:centreon-engine /etc/centreon-engine/engine-context.json
  consoleInfo "Engine context written"
  logInfo "Engine context written"
}
