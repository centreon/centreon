#!/bin/sh

#set -e
set -x

# Run each startup script located in BASEDIR.
# ls is required to ensure that the scripts are properly sorted by name.
BASEDIR="/usr/share/centreon/container.d"
for file in `ls $BASEDIR` ; do
  . "$BASEDIR/$file"
done

# Vault configuration
dnf -y install jq
. /tmp/shared-volume/vault-ids

## Get Centreon token
LOGIN_RESPONSE=$(curl -s -w "%{http_code}" \
     -H 'Content-Type:application/json' \
     -H 'Accept:application/json' \
     -d '{"security":{"credentials":{"login":"admin","password":"'"$CENTREON_CENTRAL_ADMIN_PASSWORD"'"}}}' \
     -L "http://$CENTRAL_PRIVATE_IP/centreon/api/latest/login")

TOKEN=$(echo "$RESPONSE" | head -c -4 | jq -r '.security.token')

## Register Vault
curl -X POST \
  -H 'Content-Type:application/json' \
  -H X-AUTH-TOKEN:$TOKEN \
  -H 'XDEBUG_SESSION:XDEBUG_KEY' \
  -L 'http://localhost:8080/centreon/api/latest/administration/vaults/configurations'
{
  "name": "hashicorp_vault",
  "address": "vault-custom.url.com",
  "port": 443,
  "root_path": "custom_root_path",
  "role_id": "$VAULT_ROLE_ID",
  "secret_id": "$VAULT_SECRET_ID"
}

## Migrate existing credentials
sudo -u apache php /usr/share/centreon/bin/console credentials:migrate-vault
