#!/usr/bin/env bash
# Installs a Centreon product module (e.g. centreon-anomaly-detection) into the running
# centreon/centreon stack and registers it in Centreon's DB, for qa-ticket-verify when a
# ticket's scenario needs a module the tested PR's own centreon-web-slim image doesn't ship
# (that image only ships centreon-web core — no business/product modules).
#
# Usage: install-module.sh <rpm-package-name> [module-directory-name]
#   rpm-package-name       the dnf package to install (e.g. centreon-anomaly-detection)
#   module-directory-name  the name under www/modules/ to register with
#                          install-centreon-module.php — defaults to rpm-package-name, but
#                          some modules register under a different directory than their RPM
#                          (check `rpm -ql <package> | grep www/modules` if install fails
#                          with "module directory is not installed on filesystem")
#
# Unlike centreon-modules' own install-module.sh, there's no per-module package lookup table
# here — centreon/centreon doesn't have resolve-env.sh's PR-to-module mapping, so the caller
# names the exact RPM. Modules reachable from this image's already-enabled OSS repos install
# with a plain dnf install (no extra repo/credentials needed); a module behind the business
# repo needs the same ARTIFACTORY_INTERNAL_REPO_USERNAME/PASSWORD + add-repo dance as
# centreon-modules' own install-module.sh — not implemented here since no centreon/centreon
# ticket has needed one yet.

set -euo pipefail

package=${1:?usage: install-module.sh <rpm-package-name> [module-directory-name]}
module_dir=${2:-$package}
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
compose=(docker compose -f .github/docker/docker-compose.yml)

echo "Installing $package..."
"${compose[@]}" exec -T web dnf install -y "$package"

"${compose[@]}" cp "$script_dir/install-centreon-module.php" web:/tmp/install-centreon-module.php
"${compose[@]}" exec -T web bash -c \
  "chmod 755 /tmp/install-centreon-module.php && su apache -s /bin/bash -c '/tmp/install-centreon-module.php -b /usr/share/centreon/bootstrap.php -m $module_dir'"

echo "$package installed and registered as $module_dir."
