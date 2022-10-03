#!/bin/bash
set -ex

echo `dirname $0`
mkdir "xunit-reports"

test_feature() {
    mkdir "xunit-reports-$1"

    ./vendor/bin/behat -vv --format=pretty --out=std --format=junit --out="xunit-reports-$1" $TAGS "$1"
}

[ ! -z "${TAGS}" ] && TAGS="--tags $TAGS"

WEB_IMAGE="$REGISTRY/$PROJECT-$DISTRIB:develop"
WEB_FRESH_IMAGE="$REGISTRY/$PROJECT-fresh-$DISTRIB:develop"
WEB_WIDGETS_IMAGE="$REGISTRY/packaging-widgets-$DISTRIB:22.10"

docker pull $WEB_IMAGE
docker pull $WEB_FRESH_IMAGE
docker pull $WEB_WIDGETS_IMAGE

COMPOSE_DIR="$PROJECT-$DISTRIB-$IMAGE_VERSION"
mkdir $COMPOSE_DIR

sed 's#@WEB_IMAGE@#'$WEB_IMAGE'#g' < `dirname $0`/../docker/compose/docker-compose-web.yml.in > "$COMPOSE_DIR/docker-compose-web.yml"
sed 's#@WEB_IMAGE@#'$WEB_FRESH_IMAGE'#g' < `dirname $0`/../docker/compose/docker-compose-web.yml.in > "$COMPOSE_DIR/docker-compose-web-fresh.yml"
sed 's#@WEB_IMAGE@#'$WEB_WIDGETS_IMAGE'#g' < `dirname $0`/../docker/compose/docker-compose-web.yml.in > "$COMPOSE_DIR/docker-compose-web-widgets.yml"

sed 's#@WEB_IMAGE@#'$WEB_IMAGE'#g' < `dirname $0`/../docker/compose/docker-compose-squid-simple.yml.in > "$COMPOSE_DIR/docker-compose-web-squid-simple.yml"
sed 's#@WEB_IMAGE@#'$WEB_IMAGE'#g' < `dirname $0`/../docker/compose/docker-compose-squid-basic-auth.yml.in > "$COMPOSE_DIR/docker-compose-web-squid-basic-auth.yml"
sed 's#@WEB_IMAGE@#'$WEB_IMAGE'#g' < `dirname $0`/../docker/compose/docker-compose-mediawiki.yml.in > "$COMPOSE_DIR/docker-compose-web-kb.yml"
sed 's#@WEB_IMAGE@#'$WEB_IMAGE'#g' < `dirname $0`/../docker/compose/docker-compose-openldap.yml.in > "$COMPOSE_DIR/docker-compose-web-openldap.yml"
sed 's#@WEB_IMAGE@#'$WEB_IMAGE'#g' < `dirname $0`/../docker/compose/docker-compose-influxdb.yml.in > "$COMPOSE_DIR/docker-compose-web-influxdb.yml"

cd "$COMPOSE_DIR"
alreadyset=`grep docker-compose-web.yml < `dirname $0`/../../centreon/behat.yml || true`
if [ -z "$alreadyset" ] ; then
  sed -i 's#    Centreon\\Test\\Behat\\Extensions\\ContainerExtension:#    Centreon\\Test\\Behat\\Extensions\\ContainerExtension:\n      log_directory: ../acceptance-logs\n      web: docker-compose-web.yml\n      web_fresh: docker-compose-web-fresh.yml\n      web_widgets: docker-compose-web-widgets.yml\n      web_squid_simple: docker-compose-web-squid-simple.yml\n      web_squid_basic_auth: docker-compose-web-squid-basic-auth.yml\n      web_kb: docker-compose-web-kb.yml\n      web_openldap: docker-compose-web-openldap.yml\n      web_influxdb: docker-compose-web-influxdb.yml#g' `dirname $0`/../../centreon/behat.yml
fi

rm `dirname $0`/../../centreon/features/Ldap*.feature

FEATURES=$(find `dirname $0`../../centreon/features -type f -name '*.feature' | sed -e 's#centreon/features/##g' | sort)

while read -r line; do
    echo $line
    #test_feature $line
done < <(FEATURES)