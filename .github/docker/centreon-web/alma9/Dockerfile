ARG REGISTRY_URL
ARG VERSION

FROM ${REGISTRY_URL}/centreon-web-dependencies-alma9:${VERSION} AS web_fresh

ARG VERSION

COPY --chmod=755 ./.github/docker/centreon-web/init/* /etc/init.d/

COPY --chmod=755 ./.github/docker/centreon-web/init/systemctl /bin/systemctl

COPY *.rpm /tmp/rpms-centreon/

RUN bash -e <<EOF

rm -f /tmp/rpms-centreon/centreon-${VERSION}*.rpm /tmp/rpms-centreon/centreon-central-${VERSION}*.rpm
dnf install --nogpgcheck -y /tmp/rpms-centreon/centreon-*.rpm centreon-broker-cbd centreon-broker-influxdb

echo 'date.timezone = Europe/Paris' > /etc/php.d/centreon.ini

touch /var/log/php-fpm/centreon-error.log
chown apache:apache /var/log/php-fpm/centreon-error.log

dnf clean all --enablerepo=*

EOF

COPY --chmod=755 ./.github/docker/centreon-web/alma9/entrypoint/container.sh /usr/share/centreon/container.sh
COPY --chmod=755 ./.github/docker/centreon-web/alma9/entrypoint/container.d/* /usr/share/centreon/container.d/
COPY --chmod=755 ./.github/docker/centreon-web/alma9/scripts/autoinstall.php /usr/share/centreon/
COPY --chown=apache:apache ./.github/docker/centreon-web/alma9/configuration /usr/share/centreon/www/install/tmp/

RUN <<EOF

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
rm -rf /usr/share/centreon/www/install

mysql -pcentreon -e "GRANT ALL ON *.* to 'root'@'localhost' IDENTIFIED BY '' WITH GRANT OPTION"
mysql -e "GRANT ALL ON *.* to 'root'@'%' IDENTIFIED BY 'centreon' WITH GRANT OPTION"
centreon -d -u admin -p Centreon\!2021 -a POLLERGENERATE -v 1
service mysql stop
sed -i "5s/.*/    id: 1/" /etc/centreon-gorgone/config.d/40-gorgoned.yaml

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
