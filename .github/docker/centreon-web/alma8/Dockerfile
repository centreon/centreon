ARG REGISTRY_URL
ARG VERSION

FROM ${REGISTRY_URL}/centreon-web-dependencies-alma8:${VERSION} AS web_fresh

ARG VERSION
ARG MYDUMPER_VERSION

COPY *.rpm /tmp/rpms-centreon/

COPY --chmod=755 ./.github/docker/centreon-web/alma8/entrypoint /tmp/entrypoint
COPY --chown=apache:apache ./.github/docker/centreon-web/alma8/configuration /tmp/configuration

COPY ./.github/docker/centreon-web/sql/* /usr/local/src/sql/data/

RUN bash -e <<EOF

rm -f /tmp/rpms-centreon/centreon{,-central,-mariadb,-mysql}-${VERSION}*.rpm
dnf install -y /tmp/rpms-centreon/centreon-*.rpm centreon-broker-cbd centreon-broker-influxdb

mv /tmp/entrypoint/container.sh /usr/share/centreon/container.sh
mv /tmp/entrypoint/container.d /usr/share/centreon/container.d
chmod -R 755 /usr/share/centreon/container.*

mv /tmp/configuration/* /usr/share/centreon/www/install/tmp/
chown -R apache:apache /usr/share/centreon/www/install/tmp

echo 'date.timezone = Europe/Paris' > /etc/php.d/centreon.ini

touch /var/log/php-fpm/centreon-error.log
chown apache:apache /var/log/php-fpm/centreon-error.log

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

sed -i 's#severity=error#severity=debug#' /etc/sysconfig/gorgoned
sed -i "5s/.*/    id: 1/" /etc/centreon-gorgone/config.d/40-gorgoned.yaml
sed -i 's#enable: true#enable: false#' /etc/centreon-gorgone/config.d/50-centreon-audit.yaml

dnf install -y https://github.com/mydumper/mydumper/releases/download/v${MYDUMPER_VERSION}/mydumper-${MYDUMPER_VERSION}.el8.x86_64.rpm zstd
mkdir -p /usr/local/src/sql/databases
mydumper -h localhost -P 3306 -u root -G -o /usr/local/src/sql/databases/centreon -B centreon
mydumper -h localhost -P 3306 -u root -G -o /usr/local/src/sql/databases/centreon_storage -B centreon_storage
sed -i -e 's/NO_AUTO_CREATE_USER//g' -e "s#text DEFAULT ''#text DEFAULT ('')#" /usr/local/src/sql/databases/centreon/*
sed -i -e 's/NO_AUTO_CREATE_USER//g' /usr/local/src/sql/databases/centreon_storage/*

systemctl stop gorgoned
systemctl stop centengine
systemctl stop cbd
systemctl stop httpd
systemctl stop php-fpm

service mysql stop

dnf remove -y mariadb-server galera-4
rm -rf /var/lib/mysql

dnf clean all

EOF

EXPOSE 80 3306

ENTRYPOINT ["/usr/share/centreon/container.sh"]

ENV CENTREON_DATASET="1"
