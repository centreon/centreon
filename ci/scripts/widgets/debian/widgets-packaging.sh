#!/bin/sh

set -ex

VERSION="22.10.0"
now=$(date +%s)
DISTRIB="1"

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
    (cd /src && tar czvpf - $PROJECT) | dd of=$PROJECT-$VERSION.tar.gz
    cp -rv /src/$PROJECT /build/
    cp -rv /src/$PROJECT/ci/debian /build/$PROJECT/

    ls -lart
    cd /build/$PROJECT
    COMMIT=$(git log -1 HEAD --pretty=format:%h)
    export RELEASE="$DISTRIB+$now.$COMMIT"
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

# Add here delivery of files
#
#

exit 0
