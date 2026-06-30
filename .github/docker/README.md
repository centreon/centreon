## :memo: Prerequisites

* docker
* docker compose >= 2

## :rocket: Quick start

Run following command from repository root directory:

```bash
docker compose -f .github/docker/docker-compose.yml up -d --wait
```

Centreon web should be accessible at `http://localhost:4000/centreon`

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
* `remote-server`: run a Remote Server alongside the Central, each with its own database — registers automatically (see [Remote Server setup](#satellite-remote-server-setup))
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
* `CENTREON_WEB_OS`: OS variant used to resolve the registration script path (`alma9`, `bookworm`, `jammy` — default: `alma9`)
* `REMOTE_SERVER_NAME`: name displayed for the Remote Server in the Central UI (default: `remote-server`)
* `CENTRAL_API_USERNAME`: API account used for registration (default: `admin`)
* `CENTRAL_API_PASSWORD`: password for the API account (default: `Centreon!2021`)

## :hand: Stop services

Services can be stopped with the following command:

```bash
docker compose -f .github/docker/docker-compose.yml down
```

Do not forget to specify profiles if you used them. Otherwise, additional services will not be stopped:

```bash
docker compose --profile poller --profile vault -f .github/docker/docker-compose.yml down
docker compose --profile remote-server -f .github/docker/docker-compose.yml down
```
