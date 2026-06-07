# Local TLS test stack — MON-11007 / MON-192365

Temporary local fixtures to exercise the TLS DBMS connection feature of
epic MON-11007 (ticket MON-192365). Not intended to be committed to the
repository — the `certs/` subdirectory is gitignored, and this whole
folder can be removed once testing is over.

## Layout

| File | Purpose |
|---|---|
| `gen-certs.sh` | Generates self-signed CA + server cert with SAN (one-shot). |
| `docker-compose.tls.yml` | Compose override that enables TLS on MariaDB and configures the Web client. Drives Mode 1 or Mode 2 via shell variables. |
| `.gitignore` | Excludes the `certs/` directory from git. |
| `certs/` | Generated certificates (created by `gen-certs.sh`). |

## Prerequisite — generate the certificates once

```bash
bash .github/docker/tls/gen-certs.sh
```

Produces `ca.pem`, `ca-key.pem`, `server-cert.pem`, `server-key.pem`.
The server cert SAN covers `db`, `db-remote`, `localhost`, `127.0.0.1`,
which matches the service names used by `.github/docker/docker-compose.yml`.

The script is idempotent: if certs already exist, it does nothing.
To regenerate, remove `certs/` first.

## The three modes

The implementation of TLS lives in
`centreon/src/App/Shared/Infrastructure/Database/DatabaseTLSResolver.php`.
Its branching logic defines three distinct states to validate:

### Mode 0 — No TLS (baseline)

`DATABASE_SSL_ENABLED` not set: the resolver returns an empty options
array, PDO connects in clear. Used as non-regression baseline.

```bash
docker compose -f .github/docker/docker-compose.yml down

WEB_IMAGE=docker.centreon.com/centreon/centreon-web-alma9:MON-192365 \
docker compose -f .github/docker/docker-compose.yml up -d --wait
```

Expected: `Ssl_cipher` empty.

### Mode 1 — TLS without certificate verification

`DATABASE_SSL_ENABLED=1`, `DATABASE_VERIFY_SERVER_CERT=0` (or unset).
The resolver forces TLS by setting `MYSQL_ATTR_SSL_CA = ''`, a PDO
trick that accepts self-signed certificates. Closest to the typical
OnPrem fast path.

```bash
docker compose -f .github/docker/docker-compose.yml down

WEB_IMAGE=docker.centreon.com/centreon/centreon-web-alma9:MON-192365 \
docker compose -f .github/docker/docker-compose.yml \
               -f .github/docker/tls/docker-compose.tls.yml \
               up -d --wait
```

Expected: `Ssl_cipher` non-empty (e.g. `TLS_AES_256_GCM_SHA384`),
`Ssl_version` = `TLSv1.3`.

### Mode 2 — TLS with CA verification

`DATABASE_SSL_ENABLED=1`, `DATABASE_VERIFY_SERVER_CERT=1`,
`DATABASE_CA_PATH=/certs/ca.pem`. The resolver pins the CA and the
driver verifies the server certificate chain.

```bash
docker compose -f .github/docker/docker-compose.yml down

WEB_IMAGE=docker.centreon.com/centreon/centreon-web-alma9:MON-192365 \
DATABASE_VERIFY_SERVER_CERT=1 \
DATABASE_CA_PATH=/certs/ca.pem \
docker compose -f .github/docker/docker-compose.yml \
               -f .github/docker/tls/docker-compose.tls.yml \
               up -d --wait
```

Expected: `Ssl_cipher` and `Ssl_version` filled.

### Mode 2 failure injection — wrong CA path

Same as Mode 2 but with a path that does not exist. Validates that
there is no silent fallback to clear.

```bash
docker compose -f .github/docker/docker-compose.yml down

WEB_IMAGE=docker.centreon.com/centreon/centreon-web-alma9:MON-192365 \
DATABASE_VERIFY_SERVER_CERT=1 \
DATABASE_CA_PATH=/certs/nope.pem \
docker compose -f .github/docker/docker-compose.yml \
               -f .github/docker/tls/docker-compose.tls.yml \
               up -d --wait
```

Expected: connection refused on the Centreon login page, with an
explicit PDO error in the logs. Anything else (silent retry, fallback
to clear) is a security regression.

## Helper — check the cipher of a Web PDO session

Run while the stack is up, regardless of the active mode:

```bash
docker compose -f .github/docker/docker-compose.yml exec web \
  php -r '
    $pdo = new PDO("mysql:host=db;dbname=centreon", "centreon", "centreon");
    $c = $pdo->query("SHOW STATUS LIKE \"Ssl_cipher\"")->fetch(PDO::FETCH_ASSOC);
    $v = $pdo->query("SHOW STATUS LIKE \"Ssl_version\"")->fetch(PDO::FETCH_ASSOC);
    printf("Ssl_cipher=[%s] Ssl_version=[%s]\n", $c["Value"], $v["Value"]);
  '
```

Note: this opens a fresh PDO connection from the web container, which
uses the same environment variables as the Symfony application. It is
the most faithful way to reproduce what the application sees, because
`information_schema.processlist` on MariaDB 10.11 does not expose the
SSL version of other sessions.

## Broker config generation — independent check

The TLS variables are also consumed by the broker config generator
(`www/class/config-generate/broker.class.php`, already on `develop`).
While any TLS mode is active:

```bash
docker compose -f .github/docker/docker-compose.yml exec web bash -c \
  'grep -E "db_ssl_(enabled|verify_cert|ca)" /etc/centreon-broker/*.json \
   || echo "No db_ssl_* fields found (regenerate broker config first)"'
```

Expected: the `db_ssl_enabled`, `db_ssl_verify_cert` and `db_ssl_ca`
fields appear in the `sql`, `storage` and `unified_sql` outputs of the
broker JSON, with values matching the shell environment.

## Switching modes safely

A mode change requires a clean restart, because environment variables
are read at container startup:

```bash
docker compose -f .github/docker/docker-compose.yml \
               -f .github/docker/tls/docker-compose.tls.yml down
# then up again with the new variables
```

## Cleanup

```bash
# Tear down the stack first (use the same -f flags you used to start it)
docker compose -f .github/docker/docker-compose.yml \
               -f .github/docker/tls/docker-compose.tls.yml down

# Remove the generated certificates
rm -rf .github/docker/tls/certs

# Or remove the whole folder once done
rm -rf .github/docker/tls
```
