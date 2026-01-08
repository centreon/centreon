#!/usr/bin/env bash
# Import users, acls, permissions and relations datasets for tests
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

command -v centreon >/dev/null 2>&1 || {
  echo "centreon CLI not found" >&2
  exit 1
}

CENTREON_CMD=(centreon -u admin -p 'Centreon!2021')

import() {
  local file="$1"
  [[ -f "$file" ]] || {
    echo "Missing file: $file" >&2
    exit 1
  }
  echo " ==> Importing $file"
  "${CENTREON_CMD[@]}" -i "$file"
}

# Import ACLs groups, menus, actions
import ./imports/acls/aclgroup.csv
import ./imports/acls/aclmenu.csv
import ./imports/acls/aclaction.csv

# Import ACLs permissions
import ./imports/permissions/aclactionperms.csv
import ./imports/permissions/aclmenuperms.csv

# Import ACLs relations
import ./imports/relations/aclgroup_aclaction.csv
import ./imports/relations/aclgroup_aclmenu.csv
import ./imports/relations/aclgroup_allresources.csv

# Import users
import ./imports/users/administrator.csv
import ./imports/users/editor.csv
import ./imports/users/operator.csv
import ./imports/users/unprivileged.csv
