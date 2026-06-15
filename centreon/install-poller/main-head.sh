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
# Centreon poller installer - <MAJOR>

# Fail on use of unset variables to catch typos and missing args early.
set -u

# Centreon major version
major=""

## Configuration variable
LOG_FILE="./log/install-poller.log"

# Deployment type: docker | vm
POLLER_TYPE="docker"

# Cloud mode: true (Centreon Cloud) | false (on-prem)
CLOUD_MODE="true"

# Common install args
GORGONE_TOKEN=""
GORGONE_UID=""
POLLER_NAME=""
CENTRAL_URL=""
CENTRAL_HOST=""
CENTRAL_PORT=""
APP_SECRET=""
SALT=""

# Gorgone connection (derived from the args above)
GORGONE_ADDRESS=""
GORGONE_SSL=""

# Optional / runtime
TZ=""
DEBUG=""

# Optional services (Docker mode)
WITH_VMWARE=0
WITH_SNMPTRAP=0
START_STACK=1
