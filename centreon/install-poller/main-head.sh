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
# script version <VERSION>

# Centreon major version
major=""

## Configuration variable
LOG_FILE="./log/install-poller.log"

# Deployment type: docker | vm
POLLER_TYPE="docker"

# Cloud mode: true (Centreon Cloud) | false (on-prem, future)
CLOUD_MODE="true"

# Docker mode args
POLLER_TOKEN=""
POLLER_UID=""
POLLER_NAME=""
CENTRAL_URL=""
APP_SECRET=""
SALT=""

# Optional services (Docker mode)
WITH_VMWARE=0
WITH_SNMPTRAP=0
START_STACK=1
