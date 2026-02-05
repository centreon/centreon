#!/bin/sh

if [ ! -z ${VAULT_HOST} ] && getent hosts ${VAULT_HOST}; then
  sed -i 's@"vault": [0-3]@"vault": 3@' /usr/share/centreon/config/features.json
  sed -i 's@"vault_broker": [0-3]@"vault_broker": 3@' /usr/share/centreon/config/features.json
  sed -i 's@"vault_gorgone": [0-3]@"vault_gorgone": 3@' /usr/share/centreon/config/features.json
  sed -i 's@new CurlHttpClient()@new CurlHttpClient(["verify_peer" => false, "verify_host" => false])@' /usr/share/centreon/config/centreon.config.php
  sed -i 's/default_options:/&\n            verify_host: false/' /usr/share/centreon/config/packages/framework.yaml
  sed -i 's/default_options:/&\n            verify_peer: false/' /usr/share/centreon/config/packages/framework.yaml
  sudo -u apache php /usr/share/centreon/bin/console cache:clear

  RESPONSE=$(curl -s -w "%{http_code}" -H 'Content-Type:application/json' -H 'Accept:application/json' -d '{"security":{"credentials":{"login":"admin","password":"Centreon!2021"}}}' -L "http://localhost:80/centreon/api/latest/login")
  TOKEN=$(echo "$RESPONSE" | head -c -4 | jq -r '.security.token')

  curl -X PUT \
      -H "Content-Type: application/json" \
      -H "X-AUTH-TOKEN: $TOKEN" \
      -L "http://localhost:80/centreon/api/latest/administration/vaults/configurations" \
      --data '{"address": "vault", "port": 8200, "root_path": "centreon", "role_id": "'"$VAULT_ROLE_ID"'", "secret_id": "'"$VAULT_SECRET_ID"'"}'

  php /usr/share/centreon/bin/migrateCredentials.php
fi
