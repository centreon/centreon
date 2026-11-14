#!/bin/sh
# Generates the Vault runtime files of one pod into $1 (an emptyDir), from the
# environment instead of a volume shared with the web: run as an init
# container on the fpm image (PHP + the web's own Vault classes, so the format
# is the web's by construction) by every pod that reads Vault.
#   vault.json           /var/lib/centreon/vault/vault.json for the web, gorgone,
#                        cbd and cbmod: role_id/secret_id encrypted with
#                        APP_SECRET and a per-file salt (cbd accepts nothing else)
#   features.json        the web's feature flags with Vault on (FILE_FEATURE_FLAGS)
#   ca-certificates.crt  system CA bundle + VAULT_CA_CERT: every client (PHP curl,
#                        gorgone's libcurl, cbd) verifies Vault's certificate
#   app.env              "APP_SECRET=..." line, what cbd/cbmod read (env_file
#                        defaults to /usr/share/centreon/.env, not the environment)
# Env: VAULT_ADDRESS VAULT_PORT VAULT_ROOT_PATH VAULT_ROLE_ID VAULT_SECRET_ID
#      APP_SECRET, optional VAULT_CA_CERT (PEM).
set -eu

OUT="${1:?usage: vault-config.sh <output dir>}"
: "${VAULT_ADDRESS:?}" "${VAULT_PORT:?}" "${VAULT_ROOT_PATH:?}"
: "${VAULT_ROLE_ID:?}" "${VAULT_SECRET_ID:?}" "${APP_SECRET:?}"
mkdir -p "$OUT"

php -r '
  require "/usr/share/centreon/vendor/autoload.php";
  $encryption = (new Security\Encryption())->setFirstKey(getenv("APP_SECRET"));
  $configuration = new Core\Security\Vault\Domain\Model\NewVaultConfiguration(
    $encryption,
    getenv("VAULT_ADDRESS"),
    (int) getenv("VAULT_PORT"),
    getenv("VAULT_ROOT_PATH"),
    getenv("VAULT_ROLE_ID"),
    getenv("VAULT_SECRET_ID"),
  );
  (new Core\Security\Vault\Infrastructure\Repository\FsWriteVaultConfigurationRepository(
    $argv[1] . "/vault.json",
    new Symfony\Component\Filesystem\Filesystem(),
  ))->create($configuration);
' "$OUT"

# Bit 1 = on-premise (FeatureFlags::BIT_ON_PREM).
sed -E 's/"(vault|vault_broker|vault_gorgone)": [0-9]+/"\1": 1/' \
  /usr/share/centreon/config/features.json > "$OUT/features.json"

cp /etc/ssl/certs/ca-certificates.crt "$OUT/ca-certificates.crt"
if [ -n "${VAULT_CA_CERT:-}" ]; then
  printf '%s\n' "$VAULT_CA_CERT" >> "$OUT/ca-certificates.crt"
fi

( umask 077; printf 'APP_SECRET=%s\n' "$APP_SECRET" > "$OUT/app.env" )

# Read by www-data (web), centreon-gorgone, centreon-broker and
# centreon-engine: all members of group centreon (900).
chown -R 0:900 "$OUT"
chmod 750 "$OUT"
chmod 640 "$OUT/vault.json" "$OUT/app.env"
chmod 644 "$OUT/features.json" "$OUT/ca-certificates.crt"
echo "Vault runtime files written to $OUT (vault.json, features.json, ca-certificates.crt, app.env)"
