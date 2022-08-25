#!/bin/sh

set -ex

VERSION="22.10.0"
COMMIT=`git log -1 HEAD --pretty=format:%h`
now=`date +%s`
export RELEASE="$now.$COMMIT"

if [ ! -d /root/rpmbuild/SOURCES ] ; then
    mkdir -p /root/rpmbuild/{BUILD,BUILDROOT,RPMS,SOURCES,SPECS,SRPMS}
fi

cd /src/widgets
ls -1 | while read PROJECT; do
    cd $PROJECT
    rm -rf ../$PROJECT-$VERSION
    mkdir ../$PROJECT-$VERSION
    cp -rp $PROJECT ../$PROJECT-$VERSION/
    tar czf /root/rpmbuild/SOURCES/$PROJECT-$VERSION.tar.gz ../$PROJECT-$VERSION
    rm -rf /root/rpmbuild/RPMS/*
    export SUMMARY="$(find . -name configs.xml | xargs sed -n 's|\s*<description>\(.*\)</description>|\1|p'
 2>/dev/null)"
    sed \
        -e "s/@PROJECT@/$PROJECT/g" \
        -e "s/@VERSION@/$VERSION/g" \
        -e "s/@RELEASE@/$RELEASE/g" \
        -e "s/@SUMMARY@/$SUMMARY/g" \
        ../centreon-widget.spectemplate > $PROJECT.spectemplate
    rpmbuild -ba $PROJECT.spectemplate \
        -D "VERSION $VERSION" \
        -D "RELEASE $RELEASE"
    cp -r /root/rpmbuild/RPMS/noarch/*.rpm .
    chmod 777 *.rpm
    cd ..
done

find /src -type f -iname '*.rpm' | xargs cp -vt /src/

# Add here delivery of files
#
#

exit 0
