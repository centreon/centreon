ARG REGISTRY_URL
ARG VERSION

FROM ${REGISTRY_URL}/centreon-web-dependencies-alma9:${VERSION} AS web_fresh

ARG VERSION

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

RUN bash -e <<EOF

echo "[server]
log_output=FILE
general_log_file        = /var/lib/mysql/queries.log
general_log             = 0
slow_query_log_file     = /var/lib/mysql/slow_queries.log
slow_query_log          = 1
innodb_file_per_table   = 1
innodb_flush_method     = O_DIRECT
innodb_fast_shutdown    = 0
innodb_log_file_size    = 16M
skip-name-resolve       = 1
key_buffer_size         = 16M
read_buffer_size        = 60K
sort_buffer_size        = 1M
innodb_buffer_pool_size = 64M
tmp_table_size          = 8M
max_allowed_packet      = 16M
thread_stack            = 192K
thread_cache_size       = 8
table_open_cache        = 2000
table_definition_cache  = 1400
performance_schema      = ON
open_files_limit        = 32000
" > /etc/my.cnf.d/container.cnf

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

sed -i 's#severity=error#severity=debug#' /etc/sysconfig/gorgoned
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
