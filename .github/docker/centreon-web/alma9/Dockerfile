ARG VERSION

FROM almalinux:9

ARG VERSION

RUN bash -e <<EOF

######################################
# install and configure repositories #
######################################

dnf install -y dnf-plugins-core

dnf config-manager --set-enabled crb

dnf install -y epel-release

dnf config-manager --add-repo https://packages.centreon.com/rpm-standard/${VERSION}/el9/centreon-${VERSION}.repo
dnf config-manager --set-enabled 'centreon*'

#############################
# install base dependencies #
#############################

dnf install -y \
  bc \
  brotli \
  bzip2 \
  cronie \
  httpd \
  initscripts \
  jq \
  libsodium \
  libunwind \
  lua \
  openpgm \
  net-snmp \
  net-snmp-perl \
  net-snmp-utils \
  openssl \
  python3 \
  rrdtool \
  rrdtool-perl \
  rsync \
  sed \
  sudo \
  zeromq

#############################
# install perl dependencies #
#############################

dnf install -y \
  perl \
  perl-DBD-MySQL \
  perl-DBD-SQLite \
  perl-DBI \
  perl-EV \
  perl-FFI-CheckLib \
  perl-FFI-Platypus \
  perl-HTML-Parser \
  perl-NetAddr-IP \
  perl-Net-Curl \
  perl-Net-DNS \
  perl-Net-HTTP \
  perl-Net-Ping \
  perl-Pod-Parser \
  perl-Specio \
  perl-Test-Simple \
  perl-Unicode-Collate \
  perl-XML-LibXML \
  perl-XML-SAX \
  perl-XML-Twig \
  perl-UUID \
  perltidy

#############################
# install test dependencies #
#############################

dnf install -y libfaketime

############################
# install php dependencies #
############################

dnf module enable -y php:8.1
dnf install -y \
  php-common \
  php-cli \
  php-pdo \
  php-mysqlnd \
  php-gd \
  php-xml \
  php-mbstring \
  php-ldap \
  php-snmp \
  php-intl \
  php-fpm \
  php-curl \
  php-zip \
  php-pear \
  php-json

################################
# install collect dependencies #
################################

dnf install -y \
  centreon-engine \
  centreon-broker \
  centreon-plugin-Applications-Databases-Mysql \
  centreon-plugin-Applications-Monitoring-Centreon-Central \
  centreon-plugin-Applications-Monitoring-Centreon-Database \
  centreon-plugin-Applications-Monitoring-Centreon-Map4-Jmx \
  centreon-plugin-Applications-Monitoring-Centreon-Poller \
  centreon-plugin-Applications-Protocol-Dns \
  centreon-plugin-Applications-Protocol-Ftp \
  centreon-plugin-Applications-Protocol-Http \
  centreon-plugin-Applications-Protocol-Ldap \
  centreon-plugin-Hardware-Printers-Generic-Snmp \
  centreon-plugin-Hardware-Ups-Standard-Rfc1628-Snmp \
  centreon-plugin-Network-Cisco-Standard-Snmp \
  centreon-plugin-Operatingsystems-Linux-Snmp \
  centreon-plugin-Operatingsystems-Windows-Snmp \
  nagios-plugins \
  nagios-plugins-dhcp \
  nagios-plugins-icmp

dnf clean all --enablerepo=*

EOF

COPY --chmod=755 ./.github/docker/centreon-web/init/systemctl /bin/systemctl

COPY --chmod=755 ./.github/docker/centreon-web/init/* /etc/init.d/

COPY ./.github/docker/centreon-web/sql /tmp/sql

COPY *.rpm /tmp/rpms-centreon/

COPY --chmod=755 ./.github/docker/centreon-web/alma9/entrypoint /tmp/entrypoint
COPY --chmod=755 ./.github/docker/centreon-web/alma9/scripts/autoinstall.php /tmp/autoinstall.php
COPY --chown=apache:apache ./.github/docker/centreon-web/alma9/configuration /tmp/configuration

RUN bash -e <<EOF

rm -f /tmp/rpms-centreon/centreon-${VERSION}*.rpm /tmp/rpms-centreon/centreon-central-${VERSION}*.rpm /tmp/rpms-centreon/centreon-database-${VERSION}*.rpm
dnf install -y /tmp/rpms-centreon/centreon-*.rpm

mv /tmp/entrypoint/container.sh /usr/share/centreon/container.sh
mv /tmp/entrypoint/container.d /usr/share/centreon/container.d
chmod -R 755 /usr/share/centreon/container.*

# mv /tmp/autoinstall.php /usr/share/centreon/autoinstall.php
# chmod 755 /usr/share/centreon/autoinstall.php

mkdir /usr/share/centreon/www/install/tmp
mv /tmp/configuration/* /usr/share/centreon/www/install/tmp/
chown -R centreon:centreon /usr/share/centreon/www/install/tmp

echo 'date.timezone = Europe/Paris' > /etc/php.d/centreon.ini

touch /var/log/php-fpm/centreon-error.log
chown apache:apache /var/log/php-fpm/centreon-error.log

#################################
# install and configure mariadb #
#################################

curl -LsS https://r.mariadb.com/downloads/mariadb_repo_setup \
  | bash -s -- --os-type=rhel --skip-check-installed --os-version=9 --mariadb-server-version="mariadb-10.5"
dnf install -y mariadb-server MariaDB-client

service mysql start
mysql -e "GRANT ALL ON *.* to 'root'@'localhost' IDENTIFIED BY 'centreon' WITH GRANT OPTION"

cd /usr/share/centreon/www/install/steps/process
su apache -s /bin/bash -c "php configFileSetup.php"
su apache -s /bin/bash -c "php installConfigurationDb.php"
su apache -s /bin/bash -c "php installStorageDb.php"
su apache -s /bin/bash -c "php createDbUser.php"
su apache -s /bin/bash -c "SERVER_ADDR='127.0.0.1' php insertBaseConf.php"
su apache -s /bin/bash -c "php partitionTables.php"
su apache -s /bin/bash -c "php generationCache.php"
su apache -s /bin/bash -c "rm -rf /usr/share/centreon/www/install"

mysql -pcentreon -e "GRANT ALL ON *.* to 'root'@'localhost' IDENTIFIED BY '' WITH GRANT OPTION"
mysql -e "GRANT ALL ON *.* to 'root'@'%' IDENTIFIED BY 'centreon' WITH GRANT OPTION"

mysql centreon < /tmp/sql/standard.sql
mysql centreon < /tmp/sql/media.sql
mysql centreon < /tmp/sql/openldap.sql

centreon -d -u admin -p Centreon\!2021 -a POLLERGENERATE -v 1

mysqldump --add-drop-database --databases centreon > /usr/local/src/centreon.sql
mysqldump --add-drop-database --databases centreon_storage > /usr/local/src/centreon_storage.sql

service mysql stop

dnf remove -y mariadb-server galera-4
rm -rf /var/lib/mysql

sed -i 's#severity=error#severity=debug#' /etc/sysconfig/gorgoned
sed -i "5s/.*/    id: 1/" /etc/centreon-gorgone/config.d/40-gorgoned.yaml
sed -i 's#enable: true#enable: false#' /etc/centreon-gorgone/config.d/50-centreon-audit.yaml

EOF

EXPOSE 80 3306

ENTRYPOINT ["/usr/share/centreon/container.sh"]
