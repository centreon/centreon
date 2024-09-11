#!/bin/sh

#set -e
set -x

# Run each startup script located in BASEDIR.
# ls is required to ensure that the scripts are properly sorted by name.
BASEDIR="/usr/share/centreon/container.d"
for file in `ls $BASEDIR` ; do
  . "$BASEDIR/$file"
done

# PUT HERE: Commands that will register Central server to Vault
# Should be conditionned to Web being up-and-running (health check to container)
export VAULT_ROLE_ID=$(curl --silent -H 'X-Vault-Token: $VAULT_TOKEN' $VAULT_ADDR/v1/auth/approle/role/my-role/role-id | jq -r '.data.role_id')
export VAULT_SECRET_ID=$(curl --silent -H 'X-Vault-Token: $VAULT_TOKEN' $VAULT_ADDR/v1/auth/approle/role/my-role/secret-id | jq -r '.data.secret_id')
