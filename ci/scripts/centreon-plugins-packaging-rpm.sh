#!/bin/sh

set -ex

VERSION=$(date '+%Y%m%d')
RELEASE=$(date '+%H%M%S')
PLUGIN_NAME="$3"

# Get committer.
COMMIT=$(git log -1 HEAD --pretty=format:%h)
COMMITTER=$(git show --format='%cN <%cE>' HEAD | head -n 1)

# Process plugin
perl ci/scripts/plugins-source.container.pl $PLUGIN_NAME "$VERSION ($COMMIT)"
cd ..

# Process specfile
python3 ci/scripts/create-spec-file.py $PLUGIN_NAME

if [ ! -d $HOME/rpmbuild/SOURCES ] ; then
    mkdir -p $HOME/rpmbuild/{BUILD,BUILDROOT,RPMS,SOURCES,SPECS,SRPMS}
fi

rm -rf $PLUGIN_NAME-$VERSION $PLUGIN_NAME-$VERSION.tar.gz
mv build $PLUGIN_NAME-$VERSION
tar czf $PLUGIN_NAME-$VERSION.tar.gz $PLUGIN_NAME-$VERSION

rm -rf /root/rpmbuild/RPMS/*
cd $PLUGIN_NAME-$VERSION
rpmbuild -ba ../plugin.specfile
cp -r /root/rpmbuild/RPMS/noarch/*.rpm .
chmod 777 *.rpm
cd ..

rm -rf ./output && mkdir ./output
find . -type f -iname '*.rpm' | xargs cp -vt ./output/ss

exit 0
