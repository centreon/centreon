# Directory structure

Postman collections must be stored in `collections/<feature_name>`
Additional subdirectories can be added (ex: `collections/authentication/password_policy`)

## Postman collections

Multiple collections, which are suffixed by `.postman_collection.json`, can be added in the created directories.

## Postman environment file

An environment file, which is suffixed by `.postman_environment.json`, must be added at the same level as the postman collection.

## Docker environment file (optional)

A docker environment file `.env` can be added at the same level as the postman collection.

The main usage is to set the following environment variables :
* `CENTREON_DATASET=0` # avoid creating sample resources (hosts, services...)
* `MYSQL_IMAGE=bitnami/mysql:8.3` # use mysql instead of mariadb

## Global setup script (optional)

A script `setup.sh` can be added at the same level as the postman collection.
It is run on the host machine once the web container is started and healthy (before the postman collection is run).
Usage example: `docker compose -f $(dirname $0)/../../../../../.github/docker/docker-compose.yml cp $(dirname $0)/images/my_image.png web:/usr/share/centreon/www/img/media/my_image.png` to copy image in web container

## Docker container web setup script (optional)

A script `setup-web.sh` can be added at the same level as the postman collection.
It is run in the web container once it is started and healthy (before the postman collection is run).
Usage example: `sed -i 's@"dashboard": 0@"dashboard": 3@' /usr/share/centreon/config/features.json` to update dashboard feature flags
