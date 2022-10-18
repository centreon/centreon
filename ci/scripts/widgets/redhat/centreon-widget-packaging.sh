#!/bin/sh

set -ex

PROJECT="$MODULE"

VERSION=$1
COMMIT=$2
now=`date +%s`

export RELEASE="$now.$COMMIT"

if [ ! -d /root/rpmbuild/SOURCES ] ; then
    mkdir -p /root/rpmbuild/{BUILD,BUILDROOT,RPMS,SOURCES,SPECS,SRPMS}
fi

cd /src/widgets

rm -rf /tmp/$PROJECT-$VERSION
mkdir /tmp/$PROJECT-$VERSION
cp -rp $PROJECT/* /tmp/$PROJECT-$VERSION/
(cd /tmp && tar czf /root/rpmbuild/SOURCES/$PROJECT-$VERSION.tar.gz $PROJECT-$VERSION)
rm -rf /root/rpmbuild/RPMS/*
cd $PROJECT
export WIDGET="$(echo $PROJECT | sed 's/centreon-widget-//')"
export SUMMARY="$(find . -name configs.xml | xargs sed -n 's|\s*<description>\(.*\)</description>|\1|p' 2>/dev/null)"
rpmbuild -ba ../centreon-widget.spectemplate \
    -D "version $VERSION" \
    -D "release $RELEASE" \
    -D "summary $SUMMARY" \
    -D "name $PROJECT" \
    -D "widget_sub_dir $WIDGET"
cp -r /root/rpmbuild/RPMS/noarch/*.rpm .
chmod 777 *.rpm
cd ..

find /src -type f -iname '*.rpm' | xargs cp -vt /src/

# Add here delivery of files
#
#

exit 0
