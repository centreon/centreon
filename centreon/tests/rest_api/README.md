# Directory structure

Postman collections must be stored in `collections/<feature_name>`
Additional subdirectories can be added (ex: `collections/authentication/password_policy`)

## Postman collections

Multiple collections, which are suffixed by `.postman_collection.json`, can be added in the created directories.

## Postman environment file

An environment file, which is suffixed by `.postman_environment.json`, must be added at the same level than the postman collection.

## Docker environment file (optional)

A docker environment file `.env` can be added at the same level than the postman collection.

The main usage is to set following environment variables :
* `CENTREON_DATASET=0` # avoid to create sample resources (hosts, services...)
* `MYSQL_IMAGE=bitnami/mysql:8.3` # use mysql instead of mariadb

## Docker setup script (optional)

A script `setup.sh` can be added t the same level than the postman collection.
It is run in the web container once it is started and healthy (before the postman collection is run).
Usage example: `sed -i 's@"dashboard": 0@"dashboard": 3@' /usr/share/centreon/config/features.json` in order to update dashboard feature flags
