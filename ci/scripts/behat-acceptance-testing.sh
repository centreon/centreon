#!/bin/bash
set -ex

WEB_IMAGE="$REGISTRY/$PROJECT-$DISTRIB:$RELEASE"
WEB_FRESH_IMAGE="$REGISTRY/$PROJECT-fresh-$DISTRIB:$RELEASE"
WEB_WIDGETS_IMAGE="$REGISTRY/packaging-widgets-$DISTRIB:22.10"
WEBDRIVER_IMAGE="$REGISTRY/standalone-chrome:3.141.59-oxygen"

docker pull $IMAGE
docker pull $FRESH_IMAGE
docker pull $WEB_WIDGETS_IMAGE
docker pull $WEBDRIVER_IMAGE

COMPOSE_DIR="$PROJECT-$DISTRIB-$RELEASE"
mkdir $COMPOSE_DIR

sed 's#@WEB_IMAGE@#'$WEB_IMAGE'#g' < `dirname $0`/../docker/compose/docker-compose-web.yml.in > "$COMPOSE_DIR/docker-compose-web.yml"
sed 's#@WEB_IMAGE@#'$WEB_FRESH_IMAGE'#g' < `dirname $0`/../docker/compose/docker-compose-web.yml.in > "$COMPOSE_DIR/docker-compose-web-fresh.yml"
sed 's#@WEB_IMAGE@#'$WEB_WIDGETS_IMAGE'#g' < `dirname $0`/../docker/compose/docker-compose-web.yml.in > "$COMPOSE_DIR/docker-compose-web-widgets.yml"

sed 's#@WEB_IMAGE@#'$WEB_IMAGE'#g' < `dirname $0`/../docker/compose/docker-compose-squid-simple.yml.in > "$COMPOSE_DIR/docker-compose-web-squid-simple.yml"
sed 's#@WEB_IMAGE@#'$WEB_IMAGE'#g' < `dirname $0`/../docker/docker-compose-squid-basic-auth.yml.in > "$COMPOSE_DIR/docker-compose-web-squid-basic-auth.yml"
sed 's#@WEB_IMAGE@#'$WEB_IMAGE'#g' < `dirname $0`/../docker/docker-compose-mediawiki.yml.in > "$COMPOSE_DIR/docker-compose-web-kb.yml"
sed 's#@WEB_IMAGE@#'$WEB_IMAGE'#g' < `dirname $0`/../../../containers/openldap/docker-compose.yml.in > "$COMPOSE_DIR/docker-compose-web-openldap.yml"
sed 's#@WEB_IMAGE@#'$WEB_IMAGE'#g' < `dirname $0`/../../../containers/web/22.10/docker-compose-influxdb.yml.in > "$COMPOSE_DIR/docker-compose-web-influxdb.yml"
