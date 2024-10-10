#!/bin/sh

cat /tmp/shared-volume/vault_ids
while [[ -z $VAULT_ROLE_ID ]] && [[ -z $VAULT_SECRET_ID ]]; do
  . /tmp/shared-volume/vault_ids
  sleep 5
done

RESPONSE=$(curl -s -w "%{http_code}" -H 'Content-Type:application/json' -H 'Accept:application/json' -d '{"security":{"credentials":{"login":"admin","password":"Centreon!2021"}}}' -L "http://localhost:8080/centreon/api/latest/login")
TOKEN=$(echo "$RESPONSE" | head -c -4 | jq -r '.security.token')

RESPONSE=$(curl -X POST \
     -H "Content-Type: application/json" \
     -H "X-AUTH-TOKEN: $TOKEN" \
     -L "http://localhost:8080/centreon/api/latest/administration/vaults/configurations"
     --data '{"name": "hashicorp_vault", "address": "vault", "port": 443, "root_path": "centreon/*", "role_id": "'"$VAULT_ROLE_ID"'", "secret_id": "'"$VAULT_SECRET_ID"'"}')

STATUS=$(echo "$RESPONSE" | tr -d '\n' | tail -c 3)

if [[ $STATUS -eq 200 ]]; then
  sudo -u apache php /usr/share/centreon/bin/console credentials:migrate-vault
fi
