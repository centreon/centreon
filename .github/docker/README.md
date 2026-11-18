## :memo: Prerequisites

* docker
* docker compose >= 2.20 (HTTPS mode uses the `!override` YAML tag and `service_completed_successfully`)

## :rocket: Quick start

Run following command from repository root directory:

```bash
docker compose -f .github/docker/docker-compose.yml up -d --wait
```

Centreon web should be accessible at `http://localhost:4000/centreon`

## :lock: HTTP / HTTPS mode

The stack runs in **HTTP** mode by default. HTTPS mode is opt-in via the `docker-compose.tls.yml` overlay — production-faithful, using the official Centreon HTTPS Apache vhost ([`packaging/src/centreon-apache-https.conf`](../../packaging/src/centreon-apache-https.conf)) that customer deployments run.

```bash
# HTTP (default)
docker compose -f .github/docker/docker-compose.yml up -d --wait
# → http://localhost:4000/centreon

# HTTPS
docker compose \
  -f .github/docker/docker-compose.yml \
  -f .github/docker/docker-compose.tls.yml \
  up -d --wait
# → https://localhost:4443/centreon (cert is self-signed)
```

### Ports

In HTTPS mode, **both** `:80` and `:443` are mapped. `:80` runs the production `:80 → :443` redirect block; `:443` serves the API/UI. This mirrors the production deployment shape where a user typing `http://centreon.example.com/` is redirected to `https://`.

| Service         | HTTP mode  | HTTPS mode             |
|-----------------|------------|------------------------|
| `web`           | `4000:80`  | `4000:80` + `4443:443` |
| `remote-server` | `4001:80`  | `4001:80` + `4444:443` |

Following the redirect from the host fails because the production template's redirect target preserves the request port (`%{HTTP_HOST}` → `localhost:4000`, which is HTTP-only on the host side) — this is the upstream template's behavior. To validate:

* **Redirect emission**: `curl -I http://localhost:4000/centreon/` returns `302 Found` with the expected `Location: https://localhost:4000/centreon/`.
* **Full chain at production-default ports** (inside the container): `docker compose exec -T web curl -kL http://localhost/centreon/api/latest/platform/versions` follows `:80 → :443 → 200`.

### What HTTPS mode layers on top

The overlay adds:

* **`certgen`** one-shot — issues a Root CA + multi-SAN leaf cert into the `certs` named volume on first `up` (covering `web`, `remote-server`, `db`, `db-remote`, `localhost`, `127.0.0.1`). CA persists across `up` cycles; `docker compose down -v` wipes it.
* **`web` / `remote-server`** — install the CA into the OS trust store, drop the official Apache HTTPS vhost (same cipher list, HSTS, `X-Frame-Options`, cookie hardening, FCGI to `php-fpm:9042`, `:80 → :443` redirect as production — only cert paths differ), write `/usr/share/centreon/.env` with `DATABASE_SSL_*` keys for the PHP-side [`DatabaseTLSResolver`](../../src/App/Shared/Infrastructure/Database/DatabaseTLSResolver.php), and patch the gorgone DB DSN with `mysql_ssl=1;mysql_ssl_ca_file=…;mysql_ssl_verify_server_cert=1` (RHEL images only, see the trixie note below).
* **`db` / `db-remote`** — *offer* TLS via `--ssl-cert` / `--ssl-key` but **do not** enforce it (no `--require-secure-transport=ON`). Clients that opt in (PHP via `DatabaseTLSResolver`, the `mysql` CLI via the `[client]` drop-in) negotiate TLS; the install wizard's legacy plaintext path keeps working. See [Known limitations](#known-limitations) for the rationale and how the smoke workflow guards against silent regression to all-plaintext.
* **Centreon Broker** — the install-time `db_ssl_*` JSON keys (existing Centreon support) are emitted automatically when `DATABASE_SSL_ENABLED=1` is in `.env`.

### Trusting the dev CA

```bash
# Copy the CA out of the running stack
docker compose -f .github/docker/docker-compose.yml -f .github/docker/docker-compose.tls.yml \
  cp web:/etc/pki/centreon-tls/rootCA.pem /tmp/centreon-dev-ca.pem

# Verified curl (no -k)
curl --cacert /tmp/centreon-dev-ca.pem https://localhost:4443/centreon/api/latest/platform/versions

# OS trust store (Linux example)
sudo cp /tmp/centreon-dev-ca.pem /usr/local/share/ca-certificates/centreon-dev-ca.crt
sudo update-ca-certificates
```

### Requirements

> [!NOTE]
> HTTPS mode needs `DatabaseTLSResolver` in the `WEB_IMAGE`. This class is part of `centreon-web` since the 26.07 develop/unstable line (added by [#9237](https://github.com/centreon/centreon/pull/9237)). If you point `WEB_IMAGE` at an older build, the `04-tls.sh` entrypoint hook fails fast with a copy-pasteable error so the misconfig is loud rather than silent.

### Known limitations

> [!NOTE]
> **DB TLS is offered, not enforced (no `--require-secure-transport=ON`).** The Centreon install wizard (legacy PHP path) connects to the DB before Symfony Dotenv has populated `$_ENV`, so `DatabaseTLSResolver` reads no `DATABASE_SSL_*` keys and PDO falls back to plaintext. Enforcing secure transport server-side rejects those wizard connections with a misleading "Access denied (using password: YES)" on MariaDB 10.x and the install fails before creating the schema. The pattern mirrors [centreon/centreon#10620](https://github.com/centreon/centreon/pull/10620)'s `configure_db_tls()` in `unattended.sh`. To prove TLS is still being used in practice, the smoke workflow asserts that the DB's `Ssl_accepts` global status counter is `> 0` after install — a silent regression to all-plaintext fails the smoke. Upstream follow-up: have `DatabaseTLSResolver` fall back to `getenv()` (or set `variables_order = "EGPCS"` in php-cli.ini) so the install wizard's CLI bootstrap reaches the resolver.

> [!NOTE]
> **Side-by-side HTTP + HTTPS stacks from the same checkout** is a planned future feature. Today, two `up` invocations from the same directory collide on container/volume names regardless of port mapping. Pick one mode per checkout. (Port `4443` was chosen with the future side-by-side mode in mind.)

> [!NOTE]
> **Gorgone DB-TLS on Debian images (`centreon-web-trixie`)** is currently impacted by a DBD::mysql 4.053 + libmariadb 3.x API mismatch — the old `MYSQL_OPT_SSL_ENFORCE` option was removed without a DBD::mysql-compatible replacement. Web (PHP via PR #9237), Broker, and DB-side TLS are unaffected on this image; only the gorgone Perl→DB path is impacted. On trixie the `16-tls-gorgone.sh` hook is therefore a no-op and gorgone connects to the DB in plaintext (the DB does not enforce TLS). Follow-up: gorgone support for DBD::MariaDB, which has been validated as a working alternative.

> [!NOTE]
> **DB image compatibility** — HTTPS mode has been validated against the following combinations, all passing end-to-end (web container `(healthy)`, API returns 200, all hardening headers present):
>
> | `WEB_IMAGE` (OS variant) | `MYSQL_IMAGE`                    | Status |
> |---|---|---|
> | `centreon-web-alma9`     | `bitnamilegacy/mariadb:10.11`   | ✅ |
> | `centreon-web-alma9`     | `bitnamilegacy/mysql:8.0`       | ✅ |
> | `centreon-web-alma10`    | `bitnamilegacy/mariadb:11.8`    | ✅ |
> | `centreon-web-alma10`    | `bitnamilegacy/mysql:8.4`       | ✅ |
> | `centreon-web-trixie`    | `bitnamilegacy/mariadb:11.8`    | ✅ web/broker — gorgone plaintext (see note above) |
> | `centreon-web-trixie`    | `bitnamilegacy/mysql:8.4`       | ✅ web/broker — gorgone plaintext (see note above) |
>
> Two operational notes that matter for the MySQL family in particular:
>
> 1. **`ulimits: nofile: 32000`** is set on the `db` service in the base compose. Without it, Centreon's install on bitnami MySQL images runs out of file descriptors mid-install (the PHP install scripts + dump loading open many concurrent connections + tables) and leaves the install half-complete with `Unknown database 'centreon'` errors. MariaDB images don't hit this limit.
> 2. **MySQL 8.4 servers log a `[Warning] [MY-015011] Failed to validate certificate`** at startup because the docker-compose stack does not pass `--ssl-ca` to mysqld (deliberately omitted — it was previously included but caused MySQL to embed the Root CA in its presented chain, which strict OpenSSL clients on alma9 rejected with `self-signed certificate in certificate chain`). The warning is informational; TLS continues to work normally.

### Cert generation image

The certgen image is built locally from [`.github/docker/certgen/`](certgen/Dockerfile) (alpine + openssl, same shape as [`centreon-images/.../gen_cert.sh`](https://github.com/centreon/centreon-images/blob/main/aws/centreon-ova-builder/ova_files/centreon-central/gen_cert.sh) minus the Apache / platform concerns). Override with `CERTGEN_IMAGE=…` to pin a published image.

## :toolbox: Custom database image

By default, MariaDB 11.8 is used to store Centreon data (configuration & monitoring).
This can be overridden with the `MYSQL_IMAGE` environment variable using one of the following ways:
* *Preferred*: Export environment variable directly in your terminal (ex: `export MYSQL_IMAGE=bitnami/mysql:8.1`)
* *Alternative*: Add a new line to environment file `.github/docker/.env` with MYSQL_IMAGE value (ex: `MYSQL_IMAGE=bitnami/mysql:8.1`)

> [!WARNING]
> `export` command is not available on windows, You may need to use a tool like `cross-env` or directly configure windows environment variables

## :toolbox: Custom centreon web image

By default, the Centreon web image targets the develop branch and is installed on almalinux 9.
This can be overridden with the `WEB_IMAGE` environment variable using one of the following ways:
* *Preferred*: Export the environment variable directly in your terminal (ex: `export WEB_IMAGE=docker.centreon.com/centreon/centreon-web-alma9:MON-XXX`)
* *Alternative*: Add a new line to the environment file `.github/docker/.env` with WEB_IMAGE value (ex: `docker.centreon.com/centreon/centreon-web-alma9:MON-XXX`)

> [!NOTE]
> To get an image of centreon-web on other operating system, web workflow needs to be run on a pull request which has label `system`<br/>
> Then, following image can be used: `export WEB_IMAGE=docker.centreon.com/centreon/centreon-web-bookworm:MON-XXX`

centreon-web image accepts the following environment variables:
* `CENTREON_DATASET`:
  * `0`: centreon configuration is empty
  * `1` (default value): inject minimal dataset (commands, hosts, services)
* `CENTREON_LANG`: set language to admin user
  * `en_US` (default value)
  * `fr_FR`
  * `de_DE`
  * `es_ES`
  * `pt_BR`
  * `pt_PT`
* `GORGONE_API_ADDRESS`: address of an external Gorgone API, for instance `gorgone-server` running in another container. Unset (default value): the local Perl `gorgoned` is used and nothing changes. When set:
  * the local `gorgoned` is not started, nor the loop that restarts it to register new pollers
  * the `gorgone_api_*` options of Centreon point at that address
  * the feature flag `gorgone_api_commands` is enabled: centreon-web sends its commands (external commands, configuration export, reload and restart of the pollers, SNMP traps database) through the Gorgone REST API and writes no file in `/var/lib/centreon/centcore*`, so no volume has to be shared with Gorgone
* `GORGONE_API_PORT`: port of that API (default value: `8085`)
* `GORGONE_API_USERNAME` / `GORGONE_API_PASSWORD`: credentials of that API (HTTP basic authentication), when it requires them

Usage:

```bash
CENTREON_DATASET=0 CENTREON_LANG=fr_FR docker compose -f .github/docker/docker-compose.yml up -d --wait
```

```bash
# centreon-web without its local gorgoned, calling a Gorgone API reachable as "gorgone" on the compose network
GORGONE_API_ADDRESS=gorgone docker compose -f .github/docker/docker-compose.yml up -d --wait
```

> [!NOTE]
> The remote-server import and export (`STARTWORKER` and `SENDEXPORTFILE` commands) are not available through the Gorgone API yet: they still go through the centcore files, so the `remote-server` profile needs the local `gorgoned`.

### Slim centreon web image

[`centreon-web/alma9/Dockerfile.slim`](centreon-web/alma9/Dockerfile.slim) builds centreon-web alone (PHP-FPM, Apache, crond) for a layout where Gorgone, Engine and Broker run in their own containers. It takes the application installed in the published `centreon-web-alma9` image (`WEB_IMAGE` build argument), lays the application sources of the checkout over it, and copies it into a root filesystem that holds only its run time: no package manager, Python, Perl, systemd, SNMP, rrdtool, Engine, Broker or Gorgone. The daemons are started directly by the entrypoint. The locales of the supported languages are built in (`LOCALES` build argument). It requires `GORGONE_API_ADDRESS` and `VICTORIA_METRICS_URL`: the container stops at start when one is missing.

```bash
docker build -f .github/docker/centreon-web/alma9/Dockerfile.slim -t centreon-web-slim-alma9:local .
WEB_IMAGE=centreon-web-slim-alma9:local GORGONE_API_ADDRESS=gorgone VICTORIA_METRICS_URL=http://victoria-metrics:8428 docker compose -f .github/docker/docker-compose.yml up -d --wait
```

Not available in this image: the local poller (configuration export and reload go through the Gorgone API), the remote-server profile, the Broker Graphite/InfluxDB outputs set up at startup, the MIB import (`centFillTrapDB`, Perl) and the Perl cron jobs (`eventReportBuilder`, `dashboardBuilder`: availability reports, `centreon-backup.pl`). The graphs are read from VictoriaMetrics only (below): no RRD file, so the graph images (PNG export of the legacy graph pages and widgets) and the poller statistics graphs (*Administration > Platform Status > Performances*, RRD files of the Perl Gorgone) answer that they are not available. Nothing can be installed in a running container (no `dnf`).

Graphs from VictoriaMetrics, set at each start:

* `VICTORIA_METRICS_URL`: base URL of a VictoriaMetrics API, for instance `http://victoria-metrics:8428`. Required. Every graph (API v2, dashboards, legacy graph pages, API v1 `centreon_metric` and `centreon_monitoring_metric`) is read from VictoriaMetrics (options `metrics_data_source`, `victoria_metrics_url` of *Administration > Parameters > Data*), where the `victoria_metrics` output of Broker writes them (configured in *Configuration > Pollers > Broker configuration*).
* `VICTORIA_METRICS_USER` / `VICTORIA_METRICS_PASSWORD`: HTTP basic authentication of that API, when it requires one

### FrankenPHP centreon web image

[`centreon-web/frankenphp/Dockerfile`](centreon-web/frankenphp/Dockerfile) builds the same application as the slim image as a "web server only" image: one static [FrankenPHP](https://frankenphp.dev) binary (Caddy and PHP 8.4 in classic mode, musl, built by FrankenPHP's static builder with only the PHP extensions centreon-web uses) on `scratch`. No Apache, PHP-FPM, crond, shell, package manager or MySQL client. The startup logic is PHP ([`container/container.php`](centreon-web/frankenphp/container/container.php), run by `frankenphp php-cli`), and the command picks the role:

* `serve` (default): writes `/etc/centreon/centreon.conf.php` from the environment, waits until the databases are installed, then runs FrankenPHP as `apache` on port 80 ([`Caddyfile`](centreon-web/frankenphp/Caddyfile), translated from the Apache vhost of the package). No installation, no database root access.
* `install`: one-shot. Creates the databases (or only the database user when they exist), inserts the dataset (`CENTREON_DATASET`), sets the admin language (`CENTREON_LANG`), the Gorgone API and VictoriaMetrics options, LDAP, OpenID and SAML (when their host resolves), then exits.
* `cron`: runs the PHP cron jobs of the slim image on their schedules (UTC), as `apache`, with their output in the container logs: `centAcl` and `outdated-token-removal` every minute, `downtimeManager` and `centKnowledgeSynchronizer` every 5 minutes, `centstorage_purge` at 02:00, `centreon-partitioning` at 04:00, `centreon-send-stats` at 05:12. It can run as several replicas (see *Replicas* below).

The feature flag `gorgone_api_commands` is always on in this image. `CENTREON_FEATURE_FLAGS` turns on more flags of `config/features.json`, comma separated, for instance `engine_recurring_downtimes`: the pollers then receive the recurring downtimes in their configuration (`recurringdowntimes.cfg`) and Engine schedules them, the `downtimeManager` cron sends nothing. It needs an Engine (and the central Broker, whose `unified_sql` parses the poller configuration) that knows the `recurringdowntime` object; a change to a recurring downtime applies at the next configuration export.

With `broker_managed_pki`, the BBDO connections whose TLS is on (`tls` of the poller module outputs and of the central broker input) use certificates the web issues itself: a certificate authority created on first need and the certificates of the pollers and of the central broker, all in the table `broker_pki`, private keys encrypted with the engine-context key (`CENTREON_ENGINE_CONTEXT` or `ENGINE_CONTEXT_PATH`, the same in every replica). The C++ Broker gets them as files in `pki/` of its configuration, centreon-engine inline in its structured configuration. Replacing the engine-context key makes the stored keys unreadable: the export then fails until the table is emptied, which issues a new CA and new certificates for every poller.

With `collector_enrolment` (it needs `engine_structured_configuration` too), an administrator adds a collector, a poller run by centreon-engine, with `POST /api/latest/configuration/collectors/enrolments`: `{"name": "..."}` creates the poller at once with a single-use token, `{"fleet": true, "max_uses": n}` gives a token the machines use to enrol under their host name; tokens expire (`expires_in`, 24 hours by default). The answer holds the token, shown once (gorgone-server keeps its SHA-256 only), and the `docker run` command to start the collector with nothing but the gorgone-server address and that token. The cron job `collector-enrolment` (every minute) creates the pollers of fleet enrolments as the poller wizard does, sends the first configuration (generate, move, restart) to each collector once it is connected, and deletes in gorgone-server the node of a deleted poller, which revokes its credential. Set by the `install` role into the options of the same name:

* `COLLECTOR_SERVER_URL` (`collector_server_url`): the agent gateway of gorgone-server as the collectors reach it; default `http(s)://<GORGONE_API_ADDRESS>:5556`.
* `COLLECTOR_BROKER_ADDRESS` (`collector_broker_address`): the central broker input the collectors send to; default the address of the central in the platform topology.
* `COLLECTOR_IMAGE` (`collector_image`): image of the command given to the administrator; default `centreon-engine`.
* `COLLECTOR_DOCKER_NETWORK` (`collector_docker_network`): Docker network added to that command (`--network`) when gorgone-server is only reachable through it, as in a compose stack; unset by default.

The page **Configuration > Pollers > Collectors** (React, admin only, shown with the flag) does the same from the UI: it lists the enrolment tokens (kind, uses, expiry, last use) and the collectors that used them (state, poller, address and version they reported), creates a named or fleet enrolment and shows its token and command once with a copy button, revokes a token, approves or rejects a pending fleet enrolment and asks a collector to rotate its credential. Its API, all admin only:

* `GET /configuration/collectors/enrolments`: `{"result": [{id, token_id, kind, state, approval_required, poller_id, poller_name, name, address, agent_version, created_at, updated_at}]}`; states `pending`, `approved`, `enrolled`, `completed`, `rejected`, `revoked`.
* `GET /configuration/collectors/enrolment-tokens`: `{"result": [{id, kind, poller_id, poller_name, name, expires_at, expired, max_uses, uses, spent, approval_required, created_at, last_used_at}]}`, never a token; `DELETE /configuration/collectors/enrolment-tokens/{id}` revokes one (it enrols nothing more, the collectors it enrolled keep working).
* `POST /configuration/collectors/enrolments` with `"approval_required": true` (fleet only): each machine that enrols stays `pending` until `POST /configuration/collectors/enrolments/{id}/approve` (the cron then creates its poller) or `/reject` (the collector is refused, no poller).
* `POST /configuration/collectors/{pollerId}/rotate-credential`: the collector replaces its credential with a new one it generates, at its next session.

The cron records the poller it chooses for a fleet enrolment in the table `collector_enrolment` (created on first use) before creating it, then its id: a run that dies between the creation and the assignment finds the same poller again, so a collector never gets two pollers. Runs never overlap (MariaDB lock `centreon-collector-enrolment`), whoever starts them. Test hook of the integration bench only: with `COLLECTOR_ENROLMENT_TEST_CRASH_AFTER_POLLER=1`, `cron/centreon-collector-enrolment.php` exits (code 137) right after creating a poller, as a killed cron would.

The installer of this image is the one of the base image: the `install` role adds the menu entry of the Collectors page itself (topology page 60906, as `Update-next.php` does on a package install). The React bundles are built from the checkout in the `front` stage (node 24, pnpm 10, as the CI; the pnpm store in a cache mount) and replace those of the base image, so pages added on the branch are served; node stays in that stage. Measured on the bench host: the stage takes 152 s with an empty pnpm store (2,930 packages downloaded) and 40 s with the store cached (install 20 s, rspack build about 10 s); a whole image build then takes 83 s (223 s cold). The image size does not change (365 MB: 11 MB of bundles, source maps removed, replace those of the base image).

```bash
docker build -f .github/docker/centreon-web/frankenphp/Dockerfile -t centreon-web-frankenphp:local .
```

Compose services (same environment for the three, `.env` gives the database credentials):

```yaml
  web-install:
    image: centreon-web-frankenphp:local
    command: ["install"]
    env_file: [.env]
    environment: &web-env
      MYSQL_HOST: db
      CENTREON_DATASET: ${CENTREON_DATASET:-1}
      CENTREON_LANG: ${CENTREON_LANG:-en_US}
      GORGONE_API_ADDRESS: gorgone
      VICTORIA_METRICS_URL: http://victoria-metrics:8428
    depends_on:
      db: { condition: service_healthy }
  web:
    image: centreon-web-frankenphp:local
    env_file: [.env]
    environment: *web-env
    ports: ["4000:80"]
    depends_on:
      web-install: { condition: service_completed_successfully }
    healthcheck:
      test: ["CMD", "/usr/local/bin/frankenphp", "php-cli", "/usr/local/lib/centreon-container/healthcheck.php"]
      interval: 2s
      retries: 60
      timeout: 5s
  web-cron:
    image: centreon-web-frankenphp:local
    command: ["cron"]
    env_file: [.env]
    environment: *web-env
    depends_on:
      web-install: { condition: service_completed_successfully }
```

Differences with the slim image:

* The healthcheck has no shell nor curl: the exec form above replaces `CMD-SHELL [ -f /tmp/docker.ready ] && curl …`.
* PHP errors go to the container output (`error_log = /dev/stderr`), not to `/var/log/centreon/prod.web.log`; the logs of the application stay in `/var/log/centreon` (`docker cp` to read them).
* Not available: Vault (its setup writes files of the install container that the web containers would need: `VAULT_HOST` is ignored), *Reload ACL* from the UI and CLAPI (they run `cron/centAcl.php` through a shell; the `cron` role runs it every minute), and anything else that runs a command through a shell (`centAcl.php` logs `exec(): Unable to fork` for its `ps` check at each run).
* Legacy pages are in the `CENTREON_LANG` language for every user: the gettext library of the static build takes the language from the environment (`LANG`, set from `CENTREON_LANG` at start), not from `setlocale()`. The React pages follow the language of each user.
* CLAPI runs as `apache`: `docker exec -u apache web /usr/local/bin/frankenphp php-cli /usr/share/centreon/bin/centreon -u admin -p … -a APPLYCFG -v 1` (as root, CLAPI changes file owners with `GLOB_BRACE`, which musl does not have).
* The installation steps print `session_start(): Session cannot be started after headers have already been sent` (`frankenphp php-cli`); the steps do not need the session.
* The engine context file (`engine-context.json`, the key credentials are encrypted with) is pushed to the pollers with every configuration (target `engine_context`). It comes from the environment (see *Replicas* below); without it, centreon-web creates one in `/etc/centreon` of each container (feature flag `gorgone_api_commands`), and the container warns at start that replicas would not agree on it.
* Port 80 is bound by `apache`: Docker allows it (`net.ipv4.ip_unprivileged_port_start=0`); elsewhere set that sysctl or `CENTREON_HTTP_ADDRESS` (for instance `:8080`).

PHP extensions kept: `ctype curl dom fileinfo filter gd gettext iconv intl ldap mbregex mbstring mysqlnd opcache openssl pcntl pdo pdo_mysql phar posix redis session simplexml tokenizer xml xmlwriter zip zlib` (plus the built-in `date hash json pcre random reflection spl standard`). Dropped from the slim image, referenced only by Composer packages in code paths centreon-web does not take: `bz2 calendar exif ftp mysqli pdo_sqlite readline shmop sockets sqlite3 sysvmsg sysvsem sysvshm xmlreader xsl`. `opcache` is added (absent from the slim image), `pcntl` runs the role commands and the cron scheduler, `redis` stores the sessions of replicas (`SESSION_REDIS_URL`).

#### Replicas

Several `serve` containers can run behind a load balancer (no sticky sessions needed) when they share the PHP sessions and the encryption key. The `serve` and `cron` roles read:

| Variable | Value | Unset |
|---|---|---|
| `SESSION_REDIS_URL` | `tcp://<host>:<port>` of a Redis or Valkey server, `?auth=<password>` (URL-encoded) when it needs one, or `tls://…`. The `serve` role waits up to 60 s for it, then FrankenPHP stores the sessions there (`session.save_handler = redis` through `php_ini` in the Caddyfile; same lifetime, `session.gc_maxlifetime`, and a lock per session as with files). The legacy pages and Symfony both use this native PHP session. | Local files (`/var/lib/php/session`): one replica only. |
| `CENTREON_ENGINE_CONTEXT` | The content of `engine-context.json`: `{"app_secret": "<base64>", "salt": "<base64>"}` (`app_secret`: 32 random bytes, `salt`: 64). Written at start to `/etc/centreon/engine-context.json` (`root:apache`, `0640`: centreon-web cannot replace it) and removed from the environment of FrankenPHP. | Key created on demand by each container. |
| `ENGINE_CONTEXT_PATH` | Path of that file, for instance a mounted Kubernetes Secret; it must be readable by uid or gid 48 (`apache`), e.g. `defaultMode: 0440` with `fsGroup: 48`. | |

The key is checked at start (JSON object, both values non-empty base64) and the container exits on a bad one, without printing it; setting both key variables is an error. Generate one with `php -r 'echo json_encode(["app_secret" => base64_encode(random_bytes(32)), "salt" => substr(base64_encode(random_bytes(64)), 0, 64)]), PHP_EOL;'`. On an existing platform, reuse the content of its current `engine-context.json`: the credentials already exported to the pollers are encrypted with it.

An environment variable rather than a file, for Compose: a bind mount or Compose secret is one more file of the host shared by the replicas, while the variable keeps each container self-contained; `docker inspect` shows it, so in Kubernetes prefer `ENGINE_CONTEXT_PATH` on a mounted Secret, or `CENTREON_ENGINE_CONTEXT` from `secretKeyRef`.

Several `cron` containers can run too, with the same environment and nothing more to set: each job runs once per schedule, in one of them, and when a container stops the others take its jobs from the next minute on. The `cron` role keeps one connection to the `centreon` database (application credentials) and takes MariaDB named locks (`GET_LOCK`) on it; no Redis, no table:

* For each job due in a minute, the lock `cron:<database>:<job>:<minute>` (hashed beyond 64 characters): the container that takes it runs the job, the others log `cron: <job> skipped: slot taken by another replica`. It is kept two minutes, so a container that looks at the same minute a little later skips it too.
* While the job runs, the lock `cron:<database>:<job>:running`: a run still going, in any container, makes the next ones skip (`cron: <job> skipped: still running`, or `… still running on another replica`). This also keeps two `centAcl.php` from running at once: its own guard (`pidof`, and the `cron_operation.running` flag it clears when its container has no other copy) only sees its own container; it is left unchanged.
* A lock belongs to its connection: a stopped container or a lost connection frees its locks at once, with no expiry to tune. On a lost connection the container logs it, reconnects and takes its locks back (it logs those another container took meanwhile); while the database is unreachable it skips the due jobs and logs each skip rather than run them unlocked.

The `cron` containers must use the same MariaDB server (named locks are local to a server, Galera does not replicate them), and their clocks must agree within a few seconds: the minute of a run is read from the local clock.

## :gear: Additional services using profiles

Docker compose has a useful feature which is called `profile`.
It allows to run additional services (containers) by specifying profiles which are declared in `docker-compose.yml`.
Currently, the following profiles are available:
* `poller`: register automatically a poller to centreon web image (:danger: EXPERIMENTAL)
* `poller-container`: create a poller via the API and install it with the real `centreon/install-poller` Docker flow, fully automated (see [Poller container setup](#electric_plug-poller-container-setup))
* `remote-server`: run a Remote Server alongside the Central, each with its own database — registers automatically (see [Remote Server setup](#satellite-remote-server-setup))
* `glpi`: must be used with `centreon-open-tickets` image to link glpi automatically in open-tickets providers
* `vault`: run a hashicorp vault (dev mode) and migrate credentials automatically (see [Vault setup](#lock-vault-setup))
* `openid`: run a docker image of keycloak
  * Add the following entry to your **/etc/hosts** : `127.0.0.1 sso-proxy`
  * :warning: On Windows: **C:\Windows\System32\drivers\etc\hosts**
  * centreon configuration is done automatically with auto import enabled
  * login user: **oidc** / **Centreon!2021**
  * :warning: ACLs must be configured manually
  * :warning: if you use WSL, your browser must run from WSL. Otherwise, you will not have access to openid container ip address.
* `saml`: run a docker image of keycloak
  * centreon configuration is done automatically with auto import enabled
  * login user: **saml** / **Centreon!2021**
  * :warning: ACLs must be configured manually
* `ldap`: run a docker image of ldap
  * centreon configuration is done automatically with auto import enabled
  * login user: **centreon-ldap** / **centreon-ldap** (admin bind: `cn=admin,dc=centreon,dc=com` / **centreon**)
  * :warning: ACLs must be configured manually
* `squid-simple`: run a docker image of squid without authentication (centreon configuration must be done manually)
* `squid-basic-auth`: run a docker image of squid with authentication (centreon configuration must be done manually)
* `mediawiki`: run a docker image of mediawiki (centreon configuration must be done manually)
* `graphite`: run a Graphite (`graphite-statsd`) receiver and auto-configure a Centreon Broker Graphite output pointing to it — for testing the Broker output cache
  * the Broker output is configured automatically on startup, with macro-enriched naming `centreon.metric.$INSTANCE$.$HOST$.$SERVICE$.$SERV_TAG_CAT_NAME$.$METRIC$`
  * Verify ingestion (from the web container, using the service hostname): `docker compose exec web curl -s "http://graphite/render?target=centreon.metric.**&format=json"` — after a force check, the cache-resolved host/service names must appear in the received metric paths
* `influxdb`: run an InfluxDB 1.8 receiver and auto-configure a Centreon Broker InfluxDB output pointing to it (same purpose as `graphite`)
  * Verify ingestion: `docker compose exec web curl -s -G "http://influxdb:8086/query" --data-urlencode "db=centreon" --data-urlencode "q=SHOW MEASUREMENTS"`
  * the two profiles are independent — enable `graphite`, `influxdb`, or both

> [!NOTE]
> docker image for `poller` service (`centreon-poller-alma9`) is built on centreon-collect repository<br/>

Multiple profiles can be specified in a single command:

```bash
docker compose --profile poller --profile vault -f .github/docker/docker-compose.yml up -d --wait
```

4 containers will be instantiated:
* **centreon-web**: central server with apache, php-fpm, gorgoned, centreon-engine, centreon-broker
* **database**: MariaDB by default
* **centreon-poller**: poller with gorgoned, centreon-engine (with cbmod)
* **hashicorp vault**

> [!NOTE]
> Running containers can be listed with the following command: `docker ps`<br/>
> Container terminal can be launched with the following command: `docker exec -ti <container_id> bash`<br/>
> Container logs can be displayed with the following command: `docker logs <container_id>`


## :satellite: Remote Server setup

Use the `remote-server` profile to run a Central server alongside a Remote Server, each with its own database.

Run the following command from the **repository root directory**:

```bash
docker compose --profile remote-server -f .github/docker/docker-compose.yml up -d --wait
```

Once up, both interfaces are accessible:
* Central: `http://localhost:4000/centreon`
* Remote Server: `http://localhost:4001/centreon`
* Credentials: **admin** / **Centreon!2021**

The Remote Server is fully configured automatically in the background after the containers become healthy. The following steps run without manual intervention:

1. Convert the Remote Server node type and register its topology to the Central (`registerServerTopology.sh`)
2. Link the Remote Server to the Central via the wizard API (`linkCentreonRemoteServer`), which creates the monitoring server entry and configures Broker
3. Export the initial configuration from the Central to the Remote Server
4. Retrieve the Central's Gorgone public key thumbprint
5. Generate the Gorgone ZMQ configuration on the Remote Server (`/etc/centreon-gorgone/config.d/40-gorgoned.yaml`)
6. Restart Gorgone on the Remote Server to establish the ZMQ connection with the Central
7. Generate and reload the monitoring configuration for the Remote Server from the Central
8. Restart `cbd` and `centengine` on the Remote Server to apply the generated configuration
9. Generate and reload the monitoring configuration for the Central to apply the Broker changes introduced by the wizard

The Remote Server should appear as **running** in `Configuration > Pollers` on the Central within a minute of `--wait` returning.

To follow the registration progress:

```bash
docker logs $(docker ps -qf "name=remote-server") 2>&1 | grep -i "step\|register\|link\|thumbprint\|gorgone\|generat\|running\|error\|failed"
```

The following environment variables are available to customize the setup:

* `WEB_IMAGE`: centreon-web image to use for both Central and Remote Server (default: `docker.centreon.com/centreon/centreon-web-alma9:develop`)
* `CENTREON_WEB_OS`: OS variant used to resolve entrypoint-script bind-mount paths; must match `WEB_IMAGE`'s OS (`alma9`, `alma10`, `trixie` — default: `alma9`)
* `REMOTE_SERVER_NAME`: name displayed for the Remote Server in the Central UI (default: `remote-server`)
* `CENTRAL_API_USERNAME`: API account used for registration (default: `admin`)
* `CENTRAL_API_PASSWORD`: password for the API account (default: `Centreon!2021`)

## :electric_plug: Poller container setup

Use the `poller-container` profile to test a Docker poller installed via `centreon/install-poller`'s real `--type docker` flow, without any manual UI click-through or config editing.

Run the following command from the **repository root directory**:

```bash
docker compose --profile poller-container -f .github/docker/docker-compose.yml up -d --wait
```

This runs a one-shot orchestrator container that:
1. Logs in to the Central API and creates a poller-type API token
2. Creates the poller via `POST /configuration/pollers` with `central_address: web`, so the generated install command already targets the `web` service by name — no `host.docker.internal`/`localhost` juggling needed
3. Runs the real `install.sh --type docker` with `--no-start`, attaches the generated poller stack to a shared `centreon-poller-test` Docker network, then starts it
4. Waits (up to 15 minutes) for the poller's first successful ping, checked via Gorgone's own local API on the Central (`http://web:8085/api/internal/constatus`, already enabled by default — polling `.data["<poller id>"].ping_ok`) rather than the poller's logs or raw TCP state: the poller's own Gorgone only loads the `engine`+`pullwss` modules (not `proxy`), so it cannot report its own connection status — only the Central can. Then calls `configuration/monitoring-servers/{id}/generate-and-reload` to export the poller's monitoring configuration
5. Restarts `centengine` on the poller with `docker compose exec gorgone sudo systemctl restart centengine` — `generate-and-reload` only exports the configuration, it does not restart the engine; on this split-container poller, centengine and gorgone are separate containers, so the restart goes through gorgone's systemctl shim, which relays it to the centengine container over gRPC (Gorgone doesn't run as root, hence `sudo`)

Follow the sequence in the logs:

```bash
docker compose -f .github/docker/docker-compose.yml logs poller-container
```

> [!NOTE]
> The generated install command is never logged — it embeds `--poller_token`, `--appsecret` and `--salt`, so printing it would leak those secrets into Docker logs.

The poller should appear as **running** in `Configuration > Pollers` on the Central once the orchestrator logs "Done" (Gorgone also auto-restarts in the background as soon as it detects the new poller — no manual `gorgoned` restart needed).

The following environment variables are available to customize the setup:

* `POLLER_NAME`: name of the created poller (default: `poller-container`) — use a different value to create several pollers side by side, or after a previous one was torn down
* `CENTRAL_API_USERNAME` / `CENTRAL_API_PASSWORD`: API account used for registration (default: `admin` / `Centreon!2021`)

To tear down, **two separate Compose projects** are involved and must both be torn down:

1. The poller stack itself (`centengine`/`gorgone`), generated and started by the orchestrator via the mounted Docker socket — this is its own Compose project, named after `POLLER_NAME` (`poller-container` by default), entirely separate from this repo's `docker-compose.yml`. Tear it down by project name (works even without the generated file on hand, purely from the containers'/volumes' Compose labels):
   ```bash
   docker compose -p poller-container down -v
   ```
   (replace `poller-container` with whatever `POLLER_NAME` you used)
2. Then the orchestrator's own project:
   ```bash
   docker compose --profile poller-container -f .github/docker/docker-compose.yml down -v
   ```

Doing only step 2 leaves the poller's `centengine`/`gorgone` containers running and still attached to `centreon-poller-test`, which then fails to be removed with a `Resource is still in use` warning — always run step 1 first (or anytime after) to fully clean up.

> [!NOTE]
> This profile mounts the host's Docker socket (`/var/run/docker.sock`) into the orchestrator container so it can start the poller's own `centengine`/`gorgone` containers as siblings — local dev/test only, never enabled by default or in CI.

## :lock: Vault setup

The `vault` profile runs a HashiCorp Vault (dev mode) and migrates Centreon credentials into it automatically. All the `VAULT_*` values referenced below are test credentials already set in the committed `.env`, so there is nothing to fill in.

* The `web` and `vault` services must be started together, otherwise the auto-configuration does not trigger.
* Vault UI available at `https://127.0.0.1:8200/ui` (self-signed HTTPS, accept the browser warning).
* Log in with method **Token**, using the root token from `VAULT_DEV_ROOT_TOKEN_ID` in `.env`.
* Centreon authenticates via AppRole (policy `central`, secrets stored under `centreon/`) with `VAULT_ROLE_ID` / `VAULT_SECRET_ID` from `.env`. The UI has no AppRole login, so to sign in as this identity, exchange them for a token and paste it in the Token field:

```bash
# load VAULT_ROLE_ID / VAULT_SECRET_ID from the committed .env into the shell
set -a; . .github/docker/.env; set +a
# exchange them for a Vault token (-k trusts the self-signed cert), print only the token
curl -sk -X POST --data "{\"role_id\":\"$VAULT_ROLE_ID\",\"secret_id\":\"$VAULT_SECRET_ID\"}" \
  https://127.0.0.1:8200/v1/auth/approle/login | jq -r .auth.client_token
```

## :hand: Stop services

Services can be stopped with the following command:

```bash
docker compose -f .github/docker/docker-compose.yml down
```

Do not forget to specify profiles if you used them. Otherwise, additional services will not be stopped:

```bash
docker compose --profile poller --profile vault -f .github/docker/docker-compose.yml down
docker compose --profile remote-server -f .github/docker/docker-compose.yml down
docker compose -p poller-container down -v   # tear down the poller stack first — see "Poller container setup"
docker compose --profile poller-container -f .github/docker/docker-compose.yml down -v
```
