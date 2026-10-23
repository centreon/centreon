#!/usr/bin/env bash
# Logs in as a persona (or the admin, to compare) and GETs one API path with its token —
# the "check before using it" step of references/acl-personas.md: a persona that returns the
# same thing as admin proves the grant is too wide, not that the ACL works.
#
# Usage: verify-persona.sh <login> <password> <api-path>
#   verify-persona.sh qa-user Centreon!2021User "configuration/hosts?limit=5"
#   verify-persona.sh admin 'Centreon!2021' "configuration/hosts?limit=5"   # the comparison
#
# Prints the raw JSON response on stdout; a login failure prints the login response instead
# (no token in it) and exits 1.

set -euo pipefail

login=${1:?usage: verify-persona.sh <login> <password> <api-path>}
password=${2:?usage: verify-persona.sh <login> <password> <api-path>}
api_path=${3:?usage: verify-persona.sh <login> <password> <api-path>}

base=http://localhost:4000/centreon/api/latest

token=$(curl -sf -X POST "$base/login" -H 'Content-Type: application/json' \
  -d "$(jq -n --arg l "$login" --arg p "$password" '{security:{credentials:{login:$l,password:$p}}}')" \
  | jq -r '.security.token // empty')

if [[ -z "$token" ]]; then
  echo "::error::Login failed for $login" >&2
  exit 1
fi

curl -sf "$base/$api_path" -H "X-AUTH-TOKEN: $token"
