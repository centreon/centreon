ARG REGISTRY_URL
ARG VERSION

FROM ${REGISTRY_URL}/centreon-web-dependencies-bullseye:${VERSION} AS web_fresh

ARG VERSION

ENV DEBIAN_FRONTEND noninteractive

COPY *.deb /tmp/debs-centreon/

RUN <<EOF

apt-get update

rm -f /tmp/debs-centreon/centreon-${VERSION}*.deb /tmp/debs-centreon/centreon-central-${VERSION}*.deb
apt-get install -y /tmp/debs-centreon/centreon-*.deb

echo "date.timezone = Europe/Paris" >> /etc/php/8.1/mods-available/centreon.ini

touch /var/log/php8.1-fpm-centreon-error.log
chown www-data:www-data /var/log/php8.1-fpm-centreon-error.log

apt-get clean

EOF

COPY --chmod=755 ./.github/docker/centreon-web/bullseye/entrypoint/container.sh /usr/share/centreon/container.sh
COPY --chmod=755 ./.github/docker/centreon-web/bullseye/entrypoint/container.d/* /usr/share/centreon/container.d/
COPY --chmod=755 ./.github/docker/centreon-web/bullseye/scripts/autoinstall.php /usr/share/centreon/
COPY --chown=www-data:www-data ./.github/docker/centreon-web/bullseye/configuration /usr/share/centreon/www/install/tmp/

RUN <<EOF

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
rm -rf /usr/share/centreon/www/install

mysql -pcentreon -e "GRANT ALL ON *.* to 'root'@'localhost' IDENTIFIED BY '' WITH GRANT OPTION"
mysql -e "GRANT ALL ON *.* to 'root'@'%' IDENTIFIED BY 'centreon' WITH GRANT OPTION"
centreon -d -u admin -p Centreon\!2021 -a POLLERGENERATE -v 1
service mysql stop

sed -i 's#severity=error#severity=debug#' /etc/default/gorgoned
sed -i "5s/.*/    id: 1/" /etc/centreon-gorgone/config.d/40-gorgoned.yaml
sed -i 's#enable: true#enable: false#' /etc/centreon-gorgone/config.d/50-centreon-audit.yaml

EOF

EXPOSE 80 3306

ENTRYPOINT ["/usr/share/centreon/container.sh"]

FROM web_fresh AS web_standard

COPY ./.github/docker/centreon-web/sql /tmp/sql

RUN bash -e <<EOF

service mysql start
mysql centreon < /tmp/sql/standard.sql
mysql centreon < /tmp/sql/media.sql
mysql centreon < /tmp/sql/openldap.sql
centreon -d -u admin -p Centreon\!2021 -a POLLERGENERATE -v 1
service mysql stop

EOF
