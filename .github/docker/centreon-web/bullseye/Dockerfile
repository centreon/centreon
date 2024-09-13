ARG REGISTRY_URL
ARG FROM_IMAGE_VERSION=develop

FROM ${REGISTRY_URL}/centreon-web-dependencies-bullseye:${FROM_IMAGE_VERSION}

ARG VERSION
ARG MYDUMPER_VERSION

ENV DEBIAN_FRONTEND=noninteractive

COPY *.deb /tmp/debs-centreon/

COPY --chmod=755 ./.github/docker/centreon-web/bullseye/entrypoint /tmp/entrypoint
COPY --chown=www-data:www-data ./.github/docker/centreon-web/bullseye/configuration /tmp/configuration

COPY ./.github/docker/centreon-web/sql/* /usr/local/src/sql/data/

RUN bash -e <<EOF

apt-get update

rm -f /tmp/debs-centreon/centreon{,-central,-mariadb,-mysql}_${VERSION}*.deb
apt-get install -y /tmp/debs-centreon/centreon-*.deb

mv /tmp/entrypoint/container.sh /usr/share/centreon/container.sh
mv /tmp/entrypoint/container.d /usr/share/centreon/container.d
chmod -R 755 /usr/share/centreon/container.*

mv /tmp/configuration/* /usr/share/centreon/www/install/tmp/
chown -R www-data:www-data /usr/share/centreon/www/install/tmp

echo "date.timezone = Europe/Paris" >> /etc/php/8.1/mods-available/centreon.ini

touch /var/log/php8.1-fpm-centreon-error.log
chown www-data:www-data /var/log/php8.1-fpm-centreon-error.log

sed -i 's#^datadir_set=#datadir_set=1#' /etc/init.d/mysql

service mysql start
mysql -e "GRANT ALL ON *.* to 'root'@'localhost' IDENTIFIED BY 'centreon' WITH GRANT OPTION"

cd /usr/share/centreon/www/install/steps/process
su www-data -s /bin/bash -c "php configFileSetup.php"
su www-data -s /bin/bash -c "php installConfigurationDb.php"
su www-data -s /bin/bash -c "php installStorageDb.php"
su www-data -s /bin/bash -c "php createDbUser.php"
su www-data -s /bin/bash -c "SERVER_ADDR='127.0.0.1' php insertBaseConf.php"
su www-data -s /bin/bash -c "php partitionTables.php"
su www-data -s /bin/bash -c "php generationCache.php"
su www-data -s /bin/bash -c "rm -rf /usr/share/centreon/www/install"

mysql -pcentreon -e "GRANT ALL ON *.* to 'root'@'localhost' IDENTIFIED BY '' WITH GRANT OPTION"
mysql -e "GRANT ALL ON *.* to 'root'@'%' IDENTIFIED BY 'centreon' WITH GRANT OPTION"

sed -i 's#severity=error#severity=debug#' /etc/default/gorgoned
sed -i "5s/.*/    id: 1/" /etc/centreon-gorgone/config.d/40-gorgoned.yaml
sed -i 's#enable: true#enable: false#' /etc/centreon-gorgone/config.d/50-centreon-audit.yaml

wget -P /tmp/ https://github.com/mydumper/mydumper/releases/download/v${MYDUMPER_VERSION}/mydumper_${MYDUMPER_VERSION}.bullseye_amd64.deb
apt-get install -y /tmp/mydumper_*.deb zstd
rm -f /tmp/mydumper_*
mkdir -p /usr/local/src/sql/databases
mydumper -h localhost -P 3306 -u root -G -o /usr/local/src/sql/databases/centreon -B centreon
mydumper -h localhost -P 3306 -u root -G -o /usr/local/src/sql/databases/centreon_storage -B centreon_storage
sed -i -e 's/NO_AUTO_CREATE_USER//g' -e "s#text DEFAULT ''#text DEFAULT ('')#" /usr/local/src/sql/databases/centreon/*
sed -i -e 's/NO_AUTO_CREATE_USER//g' /usr/local/src/sql/databases/centreon_storage/*

systemctl stop gorgoned
systemctl stop centengine
systemctl stop cbd
systemctl stop apache2
systemctl stop php8.1-fpm

service mysql stop

apt-get remove -y --purge mariadb-server
rm -rf /var/lib/mysql

apt-get clean

EOF

EXPOSE 80 3306

ENTRYPOINT ["/usr/share/centreon/container.sh"]

ENV CENTREON_DATASET="1"
