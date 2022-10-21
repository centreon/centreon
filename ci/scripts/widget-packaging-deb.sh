#!/bin/sh

set -ex

VERSION=$1
COMMIT=$2
WIDGET_NAME=$3
now=`date +%s`

export RELEASE="$now.$COMMIT"

AUTHOR="Luiz Costa"
AUTHOR_EMAIL="me@luizgustavo.pro.br"

cd /src/widgets
if [ -d /build ]; then
    rm -rf /build
fi
mkdir -p /build
cd /build

mkdir -p /build/$WIDGET_NAME
(cd /src/widgets && tar czvpf - $WIDGET_NAME) | dd of=$WIDGET_NAME-$VERSION.tar.gz
cp -rv /src/widgets/$WIDGET_NAME /build/
cp -rv /src/widgets/$WIDGET_NAME/ci/debian /build/$WIDGET_NAME/

ls -lart
cd /build/$WIDGET_NAME
if [ -e /build/$WIDGET_NAME/debian/substvars ]; then
    sed -i "s/^centreon:version=.*$/centreon:version=$(echo $VERSION | egrep -o '^[0-9][0-9].[0-9][0-9]')/" /build/$WIDGET_NAME/debian/substvars
fi
debmake -f "${AUTHOR}" -e "${AUTHOR_EMAIL}" -u "$VERSION" -y -r "$RELEASE"
debuild-pbuilder
cd /build

if [ -d "$RELEASE" ] ; then
    rm -rf "$RELEASE"
fi
mkdir $RELEASE
mv /build/*.deb $RELEASE/
mv /build/$RELEASE/*.deb /src

find /src -iname '*.deb'

# Add here delivery of files
#
#

exit 0
