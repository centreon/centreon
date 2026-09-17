#!/usr/bin/env bash
# Provision a disposable fake module so the legacy module-management API
# (object=centreon_module: install/details/remove) has a safe target to act on.
# Mirrors what the former CentreonModuleAPIContext Behat context did.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TARGET="/usr/share/centreon/www/modules/fake-module-to-test-api"

mkdir -p "$TARGET"
cp "$SCRIPT_DIR/modules/fake-module-to-test-api.conf.php" "$TARGET/conf.php"

# Match the web server user depending on the distribution (apache on RHEL/Alma, www-data on Debian).
if id apache >/dev/null 2>&1; then
  chown -R apache:apache "$TARGET"
else
  chown -R www-data:www-data "$TARGET"
fi
