#!/bin/sh
set -x
# export VAULT_ADDR='https://127.0.0.1:8200'
# export VAULT_SKIP_VERIFY=true
# export VAULT_TOKEN=${VAULT_DEV_ROOT_TOKEN_ID}

cat <<EOM >>/vault/config/vault.hcl
# listener "tcp" {
#   address       = "0.0.0.0:8200"
# }

disable_mlock = true
api_addr      = "https://0.0.0.0:8200"
cluster_addr  = "https://0.0.0.0:8201"
ui            = true

log_file      = "/vault/logs/vault.log"
log_level     = "info"
EOM

vault server \
  -config="/vault/config" \
  -dev-tls -non-interactive \
  -tls-skip-verify \
  -dev-listen-address="0.0.0.0:8200" \
  -log-file=/vault/logs/vault.log &

# vault secrets enable pki
# vault write pki/roles/vault-role \
#     allow_subdomains=true \
#     max_ttl="8760h" \
#     allow_any_name=true
# vault write -format=json pki/issue/vault-role \
#     common_name="vault" \
#     ttl=720h \
#     > /opt/vault/tls/vault.json
# jq -r .data.certificate /opt/vault/tls/vault_data.json > /opt/vault/tls/vault.crt
# jq -r .data.private_key /opt/vault/tls/vault_data.json > /opt/vault/tls/vault.key

# wait vault server is completely started
for i in $(seq 1 30); do grep -q 'successful mount' /vault/logs/vault.log && break || sleep 1; done

vault secrets enable -path=centreon kv
vault auth enable approle

cat <<EOM >>/vault/config/central_policy.hcl
path "centreon/*" {
  capabilities = ["create", "read", "update", "patch", "delete", "list"]
}
EOM

vault policy write central /vault/config/central_policy.hcl
vault write auth/approle/role/central token_policies="central" \
  token_ttl=1h token_max_ttl=4h

vault write auth/approle/role/central token_policies="central" token_ttl=1h token_max_ttl=4h role_id=$VAULT_ROLE_ID
vault write auth/approle/role/central/custom-secret-id ttl=0 secret_id=$VAULT_SECRET_ID

vault write auth/approle/login role_id=$VAULT_ROLE_ID secret_id=$VAULT_SECRET_ID

vault audit enable file file_path=/vault/logs/vault_audit.log

touch /tmp/docker.ready
echo "Vault is ready"

tail -f /vault/logs/vault.log

# curl --insecure --request POST \
#        --data '{"role_id": "db02de05-fa39-4855-059b-67221c5c2f63", "secret_id": "6a174c20-f6de-a53c-74d2-6018fcceff64"}' \
#        https://vault:8200/v1/auth/approle/login
