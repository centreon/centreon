#!/bin/bash
set -ex

WEB_IMAGE="$REGISTRY/$PROJECT-$DISTRIB:$IMAGE_VERSION"
WEB_FRESH_IMAGE="$REGISTRY/$PROJECT-fresh-$DISTRIB:$IMAGE_VERSION"

docker pull $WEB_IMAGE
docker pull $WEB_FRESH_IMAGE

COMPOSE_DIR="$PROJECT-$DISTRIB-$RELEASE"
mkdir $COMPOSE_DIR

sed 's#@WEB_IMAGE@#'$WEB_IMAGE'#g' < `dirname $0`/../docker/compose/docker-compose-web.yml.in > "$COMPOSE_DIR/docker-compose-web.yml"
sed 's#@WEB_IMAGE@#'$WEB_FRESH_IMAGE'#g' < `dirname $0`/../docker/compose/docker-compose-web.yml.in > "$COMPOSE_DIR/docker-compose-web-fresh.yml"
sed 's#@WEB_IMAGE@#'$WEB_WIDGETS_IMAGE'#g' < `dirname $0`/../docker/compose/docker-compose-web.yml.in > "$COMPOSE_DIR/docker-compose-web-widgets.yml"

sed 's#@WEB_IMAGE@#'$WEB_IMAGE'#g' < `dirname $0`/../docker/compose/docker-compose-squid-simple.yml.in > "$COMPOSE_DIR/docker-compose-web-squid-simple.yml"
sed 's#@WEB_IMAGE@#'$WEB_IMAGE'#g' < `dirname $0`/../docker/compose/docker-compose-squid-basic-auth.yml.in > "$COMPOSE_DIR/docker-compose-web-squid-basic-auth.yml"
sed 's#@WEB_IMAGE@#'$WEB_IMAGE'#g' < `dirname $0`/../docker/compose/docker-compose-mediawiki.yml.in > "$COMPOSE_DIR/docker-compose-web-kb.yml"
sed 's#@WEB_IMAGE@#'$WEB_IMAGE'#g' < `dirname $0`/../docker/compose/docker-compose-openldap.yml.in > "$COMPOSE_DIR/docker-compose-web-openldap.yml"
sed 's#@WEB_IMAGE@#'$WEB_IMAGE'#g' < `dirname $0`/../docker/compose/docker-compose-influxdb.yml.in > "$COMPOSE_DIR/docker-compose-web-influxdb.yml"
