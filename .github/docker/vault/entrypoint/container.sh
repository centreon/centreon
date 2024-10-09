#!/bin/sh

export VAULT_TOKEN=$(vault login ${VAULT_DEV_ROOT_TOKEN_ID} -format=json | jq -r '.auth.client_token')

export VAULT_ADDR='https://127.0.0.1:8200'
vault secrets enable -path=centreon kv
vault auth enable approle

mkdir /etc/vault.d
cat <<EOM >>/etc/vault.d/central_policy.hcl
path "centreon/*" {
  capabilities = ["create", "read", "update", "patch", "delete", "list"]
}
EOM

vault policy write central /etc/vault.d/central_policy.hcl
vault write auth/approle/role/central token_policies="central" \
  token_ttl=1h token_max_ttl=4h

export VAULT_ROLE_ID=$(vault read auth/approle/role/central/role-id -format=json | jq -r '.data.role_id')
export VAULT_SECRET_ID=$(vault write -force auth/approle/role/central/secret-id -format=json | jq -r '.data.secret_id')
vault write auth/approle/login role_id=$VAULT_ROLE_ID secret_id=$VAULT_SECRET_ID

echo "VAULT_ROLE_ID=$VAULT_ROLE_ID" >> /tmp/shared-volume/vault-ids
echo "VAULT_SECRET_ID=$VAULT_SECRET_ID" >> /tmp/shared-volume/vault-ids
