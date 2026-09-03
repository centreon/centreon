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
# Centreon poller installer - <VERSION>

# Fail on use of unset variables to catch typos and missing args early.
set -u

# Centreon major version
major=""

## Configuration variable
# curl | bash has no file on disk to locate via BASH_SOURCE[0]; use pwd instead.
WORK_DIR="$(pwd)"
LOG_FILE="${WORK_DIR}/log/install-poller.log"

# Deployment type: docker | vm (required, no default)
POLLER_TYPE=""

# Baked in at build time by build.sh
STABILITY=""

# Hidden dev/QA override: force pulling poller images from a specific
# registry regardless of STABILITY, to test a release-candidate image before
# it's promoted to ghcr.io. Not documented in help.sh on purpose.
# Valid values: "" (default, follow STABILITY), "harbor", "ghcr".
FORCE_REGISTRY=""

# Hidden dev/QA override: explicit TAG value to write into .env, regardless
# of STABILITY/FORCE_REGISTRY (e.g. "26.10.0" or "release-26.10-next").
# Empty means use the automatic default.
FORCE_TAG=""

# Hidden dev/QA override: pretend STABILITY is this value everywhere it's
# read (Docker registry/tag selection AND VM/RPM/APT repo channel config),
# without rebuilding install.sh. Useful when a major is baked "stable" but
# its packages/images aren't promoted to the stable channel/registry yet.
# Valid values: "" (default, use the real baked STABILITY), "stable",
# "testing", "unstable".
FORCE_STABILITY=""

# Cloud mode: presence of --cloud sets this to true (Centreon Cloud); absent = false (on-prem)
CLOUD_MODE="false"

# Common install args
GORGONE_TOKEN=""
GORGONE_UID=""
POLLER_NAME=""
CENTRAL_URL=""
CENTRAL_HOST=""
CENTRAL_PORT=""
# ssl inferred from the http(s):// scheme in --central_url, if any (empty = not specified)
CENTRAL_URL_SSL=""
# path segment of --central_url, if any (e.g. "/centreon"); empty means root-mounted
CENTRAL_BASE_URI=""
APP_SECRET=""
SALT=""

# Gorgone connection (derived from the args above)
GORGONE_ADDRESS=""
GORGONE_SSL=""
GORGONE_PULLWSS_CENTRAL_URI=""

# Engine broker connection port for the healthcheck (cloud: 443, on-prem: 5669)
ENGINE_PORT=""

# Optional / runtime
TZ=""
DEBUG=""

# Optional services (Docker mode)
WITH_VMWARE=0
WITH_SNMPTRAP=0
WITH_CMA=0
START_STACK=1
