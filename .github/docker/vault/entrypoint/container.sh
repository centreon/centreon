#!/bin/sh

export VAULT_ADDR='https://127.0.0.1:8200'
export VAULT_SKIP_VERIFY=true
export VAULT_TOKEN=${VAULT_DEV_ROOT_TOKEN_ID}
mkdir -p /opt/vault/tls /opt/vault/data /etc/vault.d

cat <<EOM >>/etc/vault.d/vault.hcl
storage "raft" {
  path    = "/opt/vault/data"
  node_id = "node1"
}

listener "tcp" {
  address       = "0.0.0.0:8202"
  tls_cert_file = "/opt/vault/tls/vault.crt"
  tls_key_file  = "/opt/vault/tls/vault.key"
  tls_disable   = false
}

disable_mlock = true
api_addr      = "https://0.0.0.0:8202"
cluster_addr  = "https://127.0.0.1:8202"
ui            = true
EOM

vault secrets enable pki
vault write pki/roles/vault-role \
    allow_subdomains=true \
    max_ttl="8760h" \
    allow_any_name=true
vault write -format=json pki/issue/vault-role \
    common_name="vault" \
    ttl=720h \
    > /opt/vault/tls/vault_data.json
jq -r .data.certificate /opt/vault/tls/vault_data.json > /opt/vault/tls/vault.crt
jq -r .data.private_key /opt/vault/tls/vault_data.json > /opt/vault/tls/vault.key
vault server -dev -config=/etc/vault.d/vault.hcl

vault secrets enable -path=centreon kv
vault auth enable approle

cat <<EOM >>/etc/vault.d/central_policy.hcl
path "centreon/*" {
  capabilities = ["create", "read", "update", "patch", "delete", "list"]
}
EOM

vault policy write central /etc/vault.d/central_policy.hcl
vault write auth/approle/role/central token_policies="central" \
  token_ttl=1h token_max_ttl=4h

while [[ -z $VAULT_ROLE_ID ]] || [[ -z $VAULT_SECRET_ID ]]; do
  export VAULT_ROLE_ID=$(vault read auth/approle/role/central/role-id -format=json | jq -r '.data.role_id')
  export VAULT_SECRET_ID=$(vault write -force auth/approle/role/central/secret-id -format=json | jq -r '.data.secret_id')
  sleep 5
done

if [ -f /tmp/shared-volume/vault-ids ]; then
  rm -f /tmp/shared-volume/vault-ids
fi

# Export role IDs/secret IDs to the shared volume so that they are used by the central server in the registration process
if [ ! -f /tmp/shared-volume/vault-ids ] && [[ -n $VAULT_ROLE_ID ]] && [[ -n $VAULT_SECRET_ID ]]; then
  echo "VAULT_ROLE_ID=$VAULT_ROLE_ID" >> /tmp/shared-volume/vault-ids
  echo "VAULT_SECRET_ID=$VAULT_SECRET_ID" >> /tmp/shared-volume/vault-ids
fi

vault write auth/approle/login role_id=$VAULT_ROLE_ID secret_id=$VAULT_SECRET_ID

tail -f /dev/null
