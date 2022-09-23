#!/bin/bash
set -ex

REGISTRY='docker.registry.apps.centreon.com'

WEB_IMAGE="$REGISTRY/web-$VERSION-$RELEASE:$DISTRIB"
WEB_FRESH_IMAGE="$REGISTRY/web-fresh-$VERSION-$RELEASE:$DISTRIB"
WEB_WIDGETS_IMAGE="$REGISTRY/web-widgets-$VERSION-$RELEASE:$DISTRIB"
WEBDRIVER_IMAGE="$REGISTRY/standalone-chrome:3.141.59-oxygen"

docker pull $WEB_IMAGE
docker pull $WEBDRIVER_IMAGE

