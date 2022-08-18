#!/bin/bash 

set -ex 

VERSION="22.10.0"

cd widgets
ls -1 | while read WIDGET do
  COMMIT=`git log -1 HEAD --pretty=format:%h`
  now=`date +%s`
  export RELEASE="$now.$COMMIT"

  if [ ! -d /root/rpmbuild/SOURCES ] ; then
    mkdir -p /root/rpmbuild/{BUILD,BUILDROOT,RPMS,SOURCES,SPECS,SRPMS}
  fi
  rm -rf ../$WIDGET-$VERSION
  mkdir ../$WIDGET-$VERSION
  cp -rp $WIDGET ../$WIDGET-$VERSION/
  ls -lart ../$WIDGET-$VERSION/
  tar czf /root/rpmbuild/SOURCES/$WIDGET-$VERSION.tar.gz ../$WIDGET-$VERSION
  rm -rf /root/rpmbuild/RPMS/*
  
  rpmbuild -ba xxxx/packaging/$WIDGET.spectemplate -D "VERSION $VERSION" -D "RELEASE $RELEASE"
  cp -r /root/rpmbuild/RPMS/noarch/*.rpm .
  chmod 777 *.rpm
done

exit 0
