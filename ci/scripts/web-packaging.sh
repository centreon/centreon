#!/bin/bash 

set -ex 

cd centreon
VERSION=$1
COMMIT=$2
now=`date +%s`

export RELEASE="$now.$COMMIT"

sudo composer install --no-dev --optimize-autoloader
sudo npm ci --legacy-peer-deps
sudo npm run build
find ./www/include/Administration/about -type f | xargs --delimiter='\n' sed -i -e "s/@COMMIT@/$COMMIT/g"
mkdir -p www/locale/en_US.UTF-8/LC_MESSAGES
php bin/centreon-translations.php en lang/fr_FR.UTF-8/LC_MESSAGES/messages.po www/locale/en_US.UTF-8/LC_MESSAGES/messages.ser
for i in lang/* ; do
  localefull=`basename $i`
  langcode=`echo $localefull | cut -d _ -f 1`
  mkdir -p "www/locale/$localefull/LC_MESSAGES"
  msgfmt "lang/$localefull/LC_MESSAGES/messages.po" -o "www/locale/$localefull/LC_MESSAGES/messages.mo" || exit 1
  msgfmt "lang/$localefull/LC_MESSAGES/help.po" -o "www/locale/$localefull/LC_MESSAGES/help.mo" || exit 1
  php bin/centreon-translations.php "$langcode" "lang/$localefull/LC_MESSAGES/messages.po" "www/locale/$localefull/LC_MESSAGES/messages.ser"
done
rm -rf lang
cd ..

if [ ! -d /root/rpmbuild/SOURCES ] ; then
    mkdir -p /root/rpmbuild/{BUILD,BUILDROOT,RPMS,SOURCES,SPECS,SRPMS}
fi
rm -rf ../centreon-$VERSION
mkdir ../centreon-$VERSION
cp -rp centreon ../centreon-$VERSION/
ls -lart ../centreon-$VERSION/centreon/
tar czf /root/rpmbuild/SOURCES/centreon-$VERSION.tar.gz ../centreon-$VERSION
rm -rf /root/rpmbuild/RPMS/*
cp -rp centreon/packaging/src/* /root/rpmbuild/SOURCES/
mv /root/rpmbuild/SOURCES/centreon-macroreplacement.centos7.txt /root/rpmbuild/SOURCES/centreon-macroreplacement.txt
rpmbuild -ba centreon/packaging/centreon.spectemplate -D "VERSION $VERSION" -D "RELEASE $RELEASE"
cp -r /root/rpmbuild/RPMS/noarch/*.rpm .
chmod 777 *.rpm