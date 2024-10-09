#!/bin/sh

. /tmp/shared-volume/docker_compose.env

RESPONSE=$(curl -s -w "%{http_code}" -H 'Content-Type:application/json' -H 'Accept:application/json' -d '{"security":{"credentials":{"login":"admin","password":"Centreon!2021"}}}' -L "http://localhost:8080/centreon/api/latest/login")
TOKEN=$(echo "$RESPONSE" | head -c -4 | jq -r '.security.token')

RESPONSE=$(curl -X POST \
     -H "Content-Type: application/json" \
     -H "X-AUTH-TOKEN: $TOKEN" \
     -H "XDEBUG_SESSION: XDEBUG_KEY" \
     -L "http://localhost:8080/centreon/api/latest/administration/vaults/configurations"
     --data '{"name": "hashicorp_vault", "address": "vault", "port": 443, "root_path": "centreon/*", "role_id": "'"$VAULT_ROLE_ID"'", "secret_id": "'"$VAULT_SECRET_ID"'"}')

STATUS=$(echo "$RESPONSE" | tr -d '\n' | tail -c 3)

if [[ $STATUS -eq 200 ]]; then
  sudo -u apache php /usr/share/centreon/bin/console credentials:migrate-vault
fi
