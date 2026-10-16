## :memo: Prerequisites

* docker
* docker compose >= 2

## :rocket: Quick start

Run following command from repository root directory:

```bash
docker compose -f .github/docker/docker-compose.yml up -d --wait
```

Centreon web should be accessible at `http://localhost:4000/centreon`

## :lock: HTTP / HTTPS mode

The stack runs in plain HTTP by default. HTTPS (web TLS only) is opt-in through a compose overlay:

```bash
export WEB_IMAGE=docker.centreon.com/centreon/centreon-web-alma9:dev-24.10.x
docker compose -f .github/docker/docker-compose.yml -f .github/docker/docker-compose.tls.yml up -d
```

or uncomment `COMPOSE_FILE` in `.github/docker/.env`. `WEB_IMAGE` has no default on this branch. Set `CENTREON_WEB_OS` (`alma9` default, `alma8`, `bookworm`) to match the `WEB_IMAGE` OS. Requires docker compose >= 2.20.

| Service | HTTP port | HTTPS mode             |
|---------|-----------|------------------------|
| `web`   | `4000:80` | `4000:80` + `4443:443` |

`:80` stays mapped: `curl -I http://localhost:4000/centreon/` returns the `302` to HTTPS emitted by the official Apache template.

The overlay adds:

* **`certgen`** one-shot: issues a Root CA and a leaf certificate (SAN `web`, `localhost`, `127.0.0.1`) into the `certs` named volume. `docker compose down -v` wipes it.
* **`web`**: `04-tls.sh` installs the CA in the OS trust store and drops the official Apache HTTPS vhost (only the certificate paths differ from production).

> [!NOTE]
> **Web TLS only.** The DB, gorgone and broker connections stay in plaintext on this branch.

To verify with the dev CA: `docker compose -f .github/docker/docker-compose.yml -f .github/docker/docker-compose.tls.yml cp web:/etc/pki/centreon-tls/rootCA.pem /tmp/centreon-dev-ca.pem`, then `curl --cacert /tmp/centreon-dev-ca.pem https://localhost:4443/centreon/api/latest/platform/versions`.

## :toolbox: Custom database image

By default, MariaDB 10.11 is used to store Centreon data (configuration & monitoring).
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

Usage:

```bash
CENTREON_DATASET=0 CENTREON_LANG=fr_FR docker compose -f .github/docker/docker-compose.yml up -d --wait
```

## :gear: Additional services using profiles

Docker compose has a useful feature which is called `profile`.
It allows to run additional services (containers) by specifying profiles which are declared in `docker-compose.yml`.
Currently, the following profiles are available:
* `poller`: register automatically a poller to centreon web image (:danger: EXPERIMENTAL)
* `glpi`: must be used with `centreon-open-tickets` image to link glpi automatically in open-tickets providers
* `vault`: register automatically hashicorp vault and migrate credentials
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


## :hand: Stop services

Services can be stopped with the following command:

```bash
docker compose -f .github/docker/docker-compose.yml down
```

Do not forget to specify profiles if you used it. Otherwise, additional services will not be stopped:

```bash
docker compose --profile poller --profile vault -f .github/docker/docker-compose.yml down
```
