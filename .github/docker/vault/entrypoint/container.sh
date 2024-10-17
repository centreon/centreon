#!/bin/sh

export VAULT_ADDR='https://127.0.0.1:8200'

vault server -dev -dev-listen-address="0.0.0.0:8200" &
sleep 5

export VAULT_TOKEN=${VAULT_DEV_ROOT_TOKEN_ID}
vault secrets enable -path=centreon kv
vault auth enable approle

mkdir /etc/vault.d
mkdir -p /opt/vault/tls

cat <<EOM >>/etc/vault.d/central_policy.hcl
path "centreon/*" {
  capabilities = ["create", "read", "update", "patch", "delete", "list"]
}
EOM

cat <<EOM >>/etc/vault.d/vault.hcl
storage "raft" {
  path    = "/opt/vault/data"
  node_id = "node1"
}

listener "tcp" {
  address       = "0.0.0.0:8200"
  tls_cert_file = "/opt/vault/tls/tls.crt"
  tls_key_file  = "/opt/vault/tls/tls.key"
}

api_addr     = "https://0.0.0.0:8200"
cluster_addr = "https://127.0.0.1:8201"
ui           = true
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

vault secrets enable pki
vault write -field=certificate pki/root/generate/internal \
   common_name=vault \
   issuer_name="centreon-vault" \
   ttl=8760h > /opt/vault/tls/vault.crt

vault write auth/approle/login role_id=$VAULT_ROLE_ID secret_id=$VAULT_SECRET_ID

tail -f /dev/null
