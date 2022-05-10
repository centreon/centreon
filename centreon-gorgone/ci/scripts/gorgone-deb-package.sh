#!/bin/sh
set -ex

if [ -z "$VERSION" -o -z "$RELEASE" -o -z "$DISTRIB" ] ; then
  echo "You need to specify VERSION / RELEASE / DISTRIB variables"
  exit 1
fi

echo "################################################## PACKAGING COLLECT ##################################################"

AUTHOR="Luiz Costa"
AUTHOR_EMAIL="me@luizgustavo.pro.br"

pwd

if [ -d /build ]; then
  rm -rf /build
fi
mkdir -p /build

mkdir -p /build/tmp
cd /build/tmp
apt-cache dumpavail | dpkg --merge-avail

yes | dh-make-perl make --build --version "0.11.3-${RELEASE}" --cpan Mojolicious::Plugin::BasicAuthPlus
dpkg -i libmojolicious-plugin-basicauthplus-perl_0.11.3-${RELEASE}_all.deb

yes | dh-make-perl make --build --revision ${RELEASE} --cpan ZMQ::Constants
dpkg -i libzmq-constants-perl_1.04-${RELEASE}_all.deb

git clone https://github.com/centreon-lab/zmq-libzmq4-perl.git zmq-libzmq4-perl-0.02
mkdir zmq-libzmq4-perl
mv -v zmq-libzmq4-perl-0.02 zmq-libzmq4-perl/
cd zmq-libzmq4-perl/
tar czpvf zmq-libzmq4-perl-0.02.tar.gz zmq-libzmq4-perl-0.02
cd zmq-libzmq4-perl-0.02
rm -rf debian/changelog
debmake -f "${AUTHOR}" -e "${AUTHOR_EMAIL}" -b ":perl" -r $RELEASE -y
debuild-pbuilder -uc -us
cd ..
dpkg -i zmq-libzmq4-perl_0.02-${RELEASE}_all.deb
cd /build

mkdir -p /build/centreon-gorgone
(cd /src && tar czvpf - centreon-gorgone) | dd of=centreon-gorgone-$VERSION.tar.gz
cp -rv /src/centreon-gorgone /build/
cp -rv /src/centreon-gorgone/ci/debian /build/centreon-gorgone/
sed -i "s/^centreon:version=.*$/centreon:version=${VERSION}/" /build/centreon-gorgone/debian/substvars

pwd
ls -lart
cd centreon-gorgone
debmake -f "${AUTHOR}" -e "${AUTHOR_EMAIL}" -u "$VERSION" -b ":perl" -y -r "$RELEASE"
debuild-pbuilder
cd /build

if [ -d "$DISTRIB" ] ; then
  rm -rf "$DISTRIB"
fi
mkdir $DISTRIB
mv /build/tmp/libmojolicious-plugin-basicauthplus-perl_0.11.3-${RELEASE}_all.deb $DISTRIB/
mv /build/tmp/zmq-libzmq4-perl/zmq-libzmq4-perl_0.02-${RELEASE}_all.deb $DISTRIB/
mv /build/tmp/libzmq-constants-perl_1.04-${RELEASE}_all.deb $DISTRIB/
mv /build/*.deb $DISTRIB/
mv /build/$DISTRIB/*.deb /src
