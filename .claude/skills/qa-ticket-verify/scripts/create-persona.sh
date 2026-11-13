#!/usr/bin/env bash
# Builds a non-admin persona (contact + ACL group + menu/resource/action grants) end to end,
# for a qa-ticket-verify scenario that needs to prove an ACL restriction rather than just
# being logged in as admin. Wraps the recipe in references/acl-personas.md — read that first
# for what each grant means and why an over-permissive persona proves nothing; read the
# source below before using a flag you're not sure of, rather than guessing from this comment.
#
# Usage: create-persona.sh <login> [options]
#   --password PWD            default: Centreon!2021User
#   --reach-api                allow the configuration API
#   --reach-api-rt              allow the real-time (monitoring) API
#   --menu-page PAGE           read-write menu grant, recursive (repeatable)
#   --menu-page-ro PAGE        read-only menu grant, recursive (repeatable)
#   --resource-all             grant "All Resources" (skips the other --grant-* options)
#   --grant-host NAME           (repeatable)
#   --grant-hostgroup NAME       (repeatable)
#   --grant-servicegroup NAME    (repeatable)
#   --grant-hostcategory NAME    (repeatable)
#   --grant-servicecategory NAME (repeatable)
#   --grant-metaservice NAME     (repeatable)
#   --filter-instance NAME      restrict resource access to one poller
#   --action CODES              pipe-separated action codes, e.g. "poller_listing|generate_cfg"
#                                (repeatable; merged with |)
#
# No --menu-page*/--resource-all/--action at all is valid: the persona exists and can log in,
# but sees nothing until something is granted (handy for a "this is denied" scenario). Object
# names are derived from <login> (<login>-ACLGROUP etc.) so personas from different runs don't
# collide as long as logins don't.
#
# Prints the login and password on success, so a caller that didn't set --password can still
# read it back.

set -euo pipefail

here=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
clapi() { "$here/clapi.sh" "$@"; }

login=${1:?usage: create-persona.sh <login> [options]}
shift

password=Centreon!2021User
reach_api=0
reach_api_rt=0
resource_all=0
menu_rw=()
menu_ro=()
grant_host=()
grant_hostgroup=()
grant_servicegroup=()
grant_hostcategory=()
grant_servicecategory=()
grant_metaservice=()
filter_instance=
actions=()

while [[ $# -gt 0 ]]; do
  case "$1" in
    --password) password=$2; shift 2 ;;
    --reach-api) reach_api=1; shift ;;
    --reach-api-rt) reach_api_rt=1; shift ;;
    --menu-page) menu_rw+=("$2"); shift 2 ;;
    --menu-page-ro) menu_ro+=("$2"); shift 2 ;;
    --resource-all) resource_all=1; shift ;;
    --grant-host) grant_host+=("$2"); shift 2 ;;
    --grant-hostgroup) grant_hostgroup+=("$2"); shift 2 ;;
    --grant-servicegroup) grant_servicegroup+=("$2"); shift 2 ;;
    --grant-hostcategory) grant_hostcategory+=("$2"); shift 2 ;;
    --grant-servicecategory) grant_servicecategory+=("$2"); shift 2 ;;
    --grant-metaservice) grant_metaservice+=("$2"); shift 2 ;;
    --filter-instance) filter_instance=$2; shift 2 ;;
    --action) actions+=("$2"); shift 2 ;;
    *) echo "::error::Unknown option '$1'" >&2; exit 1 ;;
  esac
done

acl_group="$login-ACLGROUP"
acl_menu="$login-ACLMENU"
acl_resource="$login-ACLRESOURCE"
acl_action="$login-ACLACTION"

echo "Creating contact $login..."
clapi CONTACT ADD "$login;$login;$login@centreon.test;$password;0;1;en_US;local"
[[ "$reach_api" == 1 ]] && clapi CONTACT SETPARAM "$login;reach_api;1"
[[ "$reach_api_rt" == 1 ]] && clapi CONTACT SETPARAM "$login;reach_api_rt;1"

clapi ACLGROUP ADD "$acl_group;$acl_group"
clapi ACLGROUP SETCONTACT "$acl_group;$login"

if [[ ${#menu_rw[@]} -gt 0 || ${#menu_ro[@]} -gt 0 ]]; then
  clapi ACLMENU ADD "$acl_menu;$acl_menu"
  for page in "${menu_rw[@]:-}"; do
    [[ -n "$page" ]] && clapi ACLMENU GRANTRW "$acl_menu;1;$page"
  done
  for page in "${menu_ro[@]:-}"; do
    [[ -n "$page" ]] && clapi ACLMENU GRANTRO "$acl_menu;1;$page"
  done
  clapi ACLGROUP ADDMENU "$acl_group;$acl_menu"
fi

if [[ "$resource_all" == 1 ]]; then
  clapi ACLGROUP ADDRESOURCE "$acl_group;All Resources"
elif [[ ${#grant_host[@]} -gt 0 || ${#grant_hostgroup[@]} -gt 0 || ${#grant_servicegroup[@]} -gt 0 \
     || ${#grant_hostcategory[@]} -gt 0 || ${#grant_servicecategory[@]} -gt 0 \
     || ${#grant_metaservice[@]} -gt 0 || -n "$filter_instance" ]]; then
  clapi ACLRESOURCE ADD "name=$acl_resource;acl_group=$acl_group"
  clapi ACLGROUP ADDRESOURCE "$acl_group;name=$acl_resource"
  for name in "${grant_host[@]:-}"; do
    [[ -n "$name" ]] && clapi ACLRESOURCE GRANT_HOST "name=$acl_resource;$name"
  done
  for name in "${grant_hostgroup[@]:-}"; do
    [[ -n "$name" ]] && clapi ACLRESOURCE GRANT_HOSTGROUP "name=$acl_resource;$name"
  done
  for name in "${grant_servicegroup[@]:-}"; do
    [[ -n "$name" ]] && clapi ACLRESOURCE GRANT_SERVICEGROUP "name=$acl_resource;$name"
  done
  for name in "${grant_hostcategory[@]:-}"; do
    [[ -n "$name" ]] && clapi ACLRESOURCE GRANT_HOSTCATEGORY "name=$acl_resource;$name"
  done
  for name in "${grant_servicecategory[@]:-}"; do
    [[ -n "$name" ]] && clapi ACLRESOURCE GRANT_SERVICECATEGORY "name=$acl_resource;$name"
  done
  for name in "${grant_metaservice[@]:-}"; do
    [[ -n "$name" ]] && clapi ACLRESOURCE GRANT_METASERVICE "name=$acl_resource;$name"
  done
  [[ -n "$filter_instance" ]] && clapi ACLRESOURCE ADDFILTER_INSTANCE "name=$acl_resource;$filter_instance"
fi

if [[ ${#actions[@]} -gt 0 ]]; then
  codes=$(IFS='|'; echo "${actions[*]}")
  clapi ACLACTION ADD "$acl_action;$acl_action"
  clapi ACLACTION GRANT "$acl_action;$codes"
  clapi ACLGROUP ADDACTION "$acl_group;$acl_action"
fi

echo "Computing rights (ACL RELOAD)..."
clapi ACL RELOAD

echo "Persona ready: login=$login password=$password"
