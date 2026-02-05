#!/bin/sh
set -x

cat <<EOM >>/vault/config/vault.hcl
disable_mlock = true
api_addr      = "https://0.0.0.0:8200"
cluster_addr  = "https://0.0.0.0:8201"
ui            = true

log_file      = "/vault/logs/vault.log"
log_level     = "info"
EOM

vault server \
  -config="/vault/config" \
  -dev-tls \
  -non-interactive \
  -tls-skip-verify \
  -dev-listen-address="0.0.0.0:8200" &

# wait vault server is completely started
for i in $(seq 1 30); do grep -q 'successful mount' /vault/logs/vault.log && break || sleep 1; done

vault secrets enable -path=centreon kv-v2
vault auth enable approle

cat <<EOM >>/vault/config/central_policy.hcl
path "centreon/*" {
  capabilities = ["create", "read", "update", "patch", "delete", "list"]
}
EOM

vault policy write central /vault/config/central_policy.hcl
vault write auth/approle/role/central token_policies="central" token_ttl=1h token_max_ttl=4h

vault write auth/approle/role/central token_policies="central" token_ttl=1h token_max_ttl=4h role_id=$VAULT_ROLE_ID
vault write auth/approle/role/central/custom-secret-id ttl=0 secret_id=$VAULT_SECRET_ID

vault write auth/approle/login role_id=$VAULT_ROLE_ID secret_id=$VAULT_SECRET_ID

touch /tmp/docker.ready
echo "Vault is ready"

tail -f /vault/logs/vault.log
