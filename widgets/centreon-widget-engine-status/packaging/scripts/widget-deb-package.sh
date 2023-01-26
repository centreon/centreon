#!/bin/sh
set -ex

PROJECT="centreon-widget-engine-status"

if [ -z "$VERSION" -o -z "$RELEASE" -o -z "$DISTRIB" ] ; then
  echo "You need to specify VERSION / RELEASE / DISTRIB variables"
  exit 1
fi

echo "################################################## PACKAGING COLLECT ##################################################"

AUTHOR="Centreon"
AUTHOR_EMAIL="contact@centreon.com"

if [ -d /build ]; then
  rm -rf /build
fi
mkdir -p /build
cd /build

mkdir -p /build/$PROJECT
(cd /src && tar czvpf - $PROJECT) | dd of=$PROJECT-$VERSION.tar.gz
cp -rv /src/$PROJECT /build/
cp -rv /src/$PROJECT/ci/debian /build/$PROJECT/

ls -lart
cd /build/$PROJECT
debmake -f "${AUTHOR}" -e "${AUTHOR_EMAIL}" -u "$VERSION" -y -r "$DISTRIB"
debuild-pbuilder
cd /build

if [ -d "$DISTRIB" ] ; then
  rm -rf "$DISTRIB"
fi
mkdir $DISTRIB
mv /build/*.deb $DISTRIB/
mv /build/$DISTRIB/*.deb /src
