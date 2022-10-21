#!/bin/sh

set -ex

VERSION=$1
RELEASE=$2
WIDGET_NAME=`echo "$3" | sed "#centreon-widget/##"`

if [ ! -d /root/rpmbuild/SOURCES ] ; then
    mkdir -p /root/rpmbuild/{BUILD,BUILDROOT,RPMS,SOURCES,SPECS,SRPMS}
fi

cd /src/widgets
rm -rf /tmp/$WIDGET_NAME-$VERSION
mkdir /tmp/$WIDGET_NAME-$VERSION
cp -rp $WIDGET_NAME/* /tmp/$WIDGET_NAME-$VERSION/
(cd /tmp && tar czf /root/rpmbuild/SOURCES/$WIDGET_NAME-$VERSION.tar.gz $WIDGET_NAME-$VERSION)
rm -rf /root/rpmbuild/RPMS/*
cd $WIDGET_NAME
export WIDGET="$(echo $WIDGET_NAME | sed 's/centreon-widget-//')"
export SUMMARY="$(find . -name configs.xml | xargs sed -n 's|\s*<description>\(.*\)</description>|\1|p'
2>/dev/null)"
rpmbuild -ba ../centreon-widget.spectemplate \
    -D "version $VERSION" \
    -D "release $RELEASE" \
    -D "summary $SUMMARY" \
    -D "name $WIDGET_NAME" \
    -D "widget_sub_dir $WIDGET"
cp -r /root/rpmbuild/RPMS/noarch/*.rpm .
chmod 777 *.rpm
cd ..

find /src -type f -iname '*.rpm' | xargs cp -vt /src/

# Add here delivery of files
#
#

exit 0
