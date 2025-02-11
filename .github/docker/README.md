

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
This can be overrided with environment variable `MYSQL_IMAGE` using one of the following way:
* Preferred: Export environment variable directly in your terminal (ex: `export MYSQL_IMAGE=bitnami/mysql:8.1`)
* Alternative: Add a new line to environment file `.github/docker/.env` with MYSQL_IMAGE value (ex: `MYSQL_IMAGE=bitnami/mysql:8.1`)


## :toolbox: Custom centreon web image

By default, Centreon web image targets develop branch and is installed on almalinux 9.
This can be overrided with environment variable `WEB_IMAGE` using one of the following way:
* Preferred: Export environment variable directly in your terminal (ex: `export WEB_IMAGE=docker.centreon.com/centreon/centreon-web-alma9:MON-XXX`)
* Alternative: Add a new line to environment file `.github/docker/.env` with MYSQL_IMAGE value (ex: `docker.centreon.com/centreon/centreon-web-alma9:MON-XXX`)

> [!NOTE]
> To get an image of centreon-web on other operating system, web workflow needs to be run on a pull request which has label `system`
> Then, following image can be used: `export WEB_IMAGE=docker.centreon.com/centreon/centreon-web-bookworm:MON-XXX`



## :gear: Additional services using profiles

Docker compose has a useful feature which is called `profile`.
It allows to run additional services (containers) by specifying profiles which are declared in `docker-compose.yml`.
Currently, following profiles exist:
* `poller`: register automatically a poller to centreon web image (:danger: EXPERIMENTAL)
* `glpi`: must be used with `centreon-open-tickets` image to link glpi automatically in open-tickets providers
* `vault`: register automatically hashicorp vault and migrate credentials
* `openid`: run a docker image of keycloak (centreon configuration must be done manually)
* `saml`: run a docker image of keycloak (centreon configuration must be done manually)
* `openldap`: run a docker image of openldap (centreon ldap configuration must be enabled manually)
* `squid-simple`: run a docker image of squid without authentication (centreon configuration must be done manually)
* `squid-basic-auth`: run a docker image of squid with authentication (centreon configuration must be done manually)
* `mediawiki`: run a docker image of mediawiki (centreon configuration must be done manually)

Multiple profiles can be specified in a single command:

```bash
docker compose --profile poller --profile vault -f .github/docker/docker-compose.yml up -d --wait
```

4 containers will be instanciated:
* centreon-web: central server with apache, php-fpm, gorgoned, centreon-engine, centreon-broker
* database: MariaDB by default
* centreon-poller: poller with gorgoned, centreon-engine (with cbmod)
* hashicorp vault


## Additional services using profiles

Services can be stopped with the following command:

```bash
docker compose -f .github/docker/docker-compose.yml down
```

Do not forget to specify profiles if you used it. Otherwise, additional services will not be stopped:

```bash
docker compose --profile poller --profile vault -f .github/docker/docker-compose.yml down
```
