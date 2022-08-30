#!/bin/sh

set -ex

VERSION="22.10.0"
now=$(date +%s)

if [ ! -d /root/rpmbuild/SOURCES ] ; then
    mkdir -p /root/rpmbuild/{BUILD,BUILDROOT,RPMS,SOURCES,SPECS,SRPMS}
fi

cd /src/widgets
ls -1 | sed '/centreon-widget.spectemplate/d' | while read PROJECT; do
    rm -rf /tmp/$PROJECT-$VERSION
    mkdir /tmp/$PROJECT-$VERSION
    cp -rp $PROJECT/* /tmp/$PROJECT-$VERSION/
    (cd /tmp && tar czf /root/rpmbuild/SOURCES/$PROJECT-$VERSION.tar.gz $PROJECT-$VERSION)
    rm -rf /root/rpmbuild/RPMS/*
    cd $PROJECT
    export WIDGET="$(echo $PROJECT | sed 's/centreon-widget-//')"
    COMMIT=$(git log -1 HEAD --pretty=format:%h)
    export RELEASE="$now.$COMMIT"
    export SUMMARY="$(find . -name configs.xml | xargs sed -n 's|\s*<description>\(.*\)</description>|\1|p'
 2>/dev/null)"
    sed \
        -e "s/@PROJECT@/$PROJECT/g" \
        -e "s/@WIDGET@/$WIDGET/g" \
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
