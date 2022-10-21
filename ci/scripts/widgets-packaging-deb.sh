#!/bin/sh

set -ex

VERSION=$1
COMMIT=$2
now=`date +%s`

export RELEASE="$now.$COMMIT"

AUTHOR="Luiz Costa"
AUTHOR_EMAIL="me@luizgustavo.pro.br"

cd /src/widgets
ls -1 | sed '/centreon-widget.spectemplate/d' | while read PROJECT; do
    if [ -d /build ]; then
        rm -rf /build
    fi
    mkdir -p /build
    cd /build

    mkdir -p /build/$PROJECT
    (cd /src/widgets && tar czvpf - $PROJECT) | dd of=$PROJECT-$VERSION.tar.gz
    cp -rv /src/widgets/$PROJECT /build/
    cp -rv /src/widgets/$PROJECT/ci/debian /build/$PROJECT/

    ls -lart
    cd /build/$PROJECT
    if [ -e /build/$PROJECT/debian/substvars ]; then
        sed -i "s/^centreon:version=.*$/centreon:version=$(echo $VERSION | egrep -o '^[0-9][0-9].[0-9][0-9]')/" /build/$PROJECT/debian/substvars
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
done

find /src -iname '*.deb'

# Add here delivery of files
#
#

exit 0
