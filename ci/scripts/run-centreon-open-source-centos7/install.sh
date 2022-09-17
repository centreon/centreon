#!/bin/bash 

set -ex 

yum clean all
yum install -y /tmp/*.rpm centreon-broker-cbd centreon-broker-influxdb
# systemctl enable mariadb php-fpm httpd24-httpd gorgoned centreontrapd cbd centengine centreon
# systemctl restart mariadb php-fpm httpd24-httpd gorgoned cbd centengine

cp /tmp/centengine.sh /etc/init.d/centengine
cp /tmp/cbd.sh /etc/init.d/cbd
chmod +x /etc/init.d/centengine /etc/init.d/cbd
cp /tmp/autoinstall.php /usr/share/centreon/autoinstall.php
cp /tmp/configuration/* /usr/share/centreon/www/install/tmp/
chown -R apache.apache /usr/share/centreon/www/install/tmp
touch /var/log/php-fpm/centreon-error.log
chown apache:apache /var/log/php-fpm/centreon-error.log
cp -r /tmp/run /usr/share/centreon/container.d

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
centreon -d -u admin -p Centreon\!2021 -a CFGMOVE -v 1


cp /tmp/run.sh /usr/share/centreon/container.sh
chmod +x /usr/share/centreon/container.sh
yum clean all
rm -rf /tmp/fresh


