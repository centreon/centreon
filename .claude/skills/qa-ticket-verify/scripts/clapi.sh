#!/usr/bin/env bash
# Runs one CLAPI call in the running "web" container, authenticated as the e2e admin.
#
# Usage: clapi.sh <object> <action> [values]
#   clapi.sh CONTACT ADD "qa-user;qa-user;qa-user@centreon.test;Centreon!2021User;0;1;en_US;local"
#   clapi.sh ACL RELOAD
#
# Prints nothing and exits 0 on success; a non-zero exit prints CLAPI's own reason (e.g.
# "Object not found"). Used directly for one-off calls, and by create-persona.sh for the
# common end-to-end persona build — see references/acl-personas.md for the full recipe and
# the grant shapes this doesn't wrap (GRANT_HOSTGROUP, GRANT_SERVICEGROUP, etc. all follow
# the same ACLRESOURCE GRANT_* pattern as GRANT_HOST below).

set -euo pipefail

object=${1:?usage: clapi.sh <object> <action> [values]}
action=${2:?usage: clapi.sh <object> <action> [values]}
values=${3:-}

docker compose -f .github/docker/docker-compose.yml exec -T web \
  su apache -s /bin/bash -c "centreon -u admin -p 'Centreon!2021' -o $object -a $action -v \"$values\""
