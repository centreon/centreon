#!/bin/sh

# Delete ib_logfiles to avoid read/rebuild time on startup (~9s)
# rm -f /var/lib/mysql/ib_logfile*

# # Start database server.
# service mysql start

# # Wait for the database to be up and running.
# while true ; do
#   timeout 10 mysql -e 'SELECT id FROM nagios_server' centreon
#   retval=$?
#   if [ "$retval" = 0 ] ; then
#     break ;
#   else
#     echo 'DB server is not yet responding.'
#     sleep 1
#   fi
# done

# Wait for the database to be up and running.
while true ; do
  timeout 20 mysql -h${MYSQL_HOST} -uroot -p${MYSQL_ROOT_PASSWORD} -e 'SELECT User FROM user' mysql
  retval=$?
  if [ "$retval" = 0 ] ; then
    break ;
  else
    echo 'DB server is not yet responding.'
    sleep 1
  fi
done

DATABASES_DUMP_DIR="/usr/local/src/sql/databases"
for file in `ls $DATABASES_DUMP_DIR` ; do
  # mysql -h${MYSQL_HOST} -uroot -p${MYSQL_ROOT_PASSWORD} <<< "SET autocommit=0; source $DATABASES_DUMP_DIR/$file; COMMIT;"
  myloader -h ${MYSQL_HOST} -u root -p ${MYSQL_ROOT_PASSWORD} -P 3306 -s $file -o -v 3 -d $DATABASES_DUMP_DIR/$file
done

DATA_DUMP_DIR="/usr/local/src/sql/data"
for file in `ls $DATA_DUMP_DIR` ; do
  mysql -h${MYSQL_HOST} -uroot -p${MYSQL_ROOT_PASSWORD} centreon < $DATA_DUMP_DIR/$file
done

mysql -h${MYSQL_HOST} -uroot -p${MYSQL_ROOT_PASSWORD} centreon -e "UPDATE cfg_centreonbroker_info SET config_value = '${MYSQL_HOST}' WHERE config_key = 'db_host'"

#mysql -h${MYSQL_HOST} -uroot -p${MYSQL_ROOT_PASSWORD} -e "GRANT ALL ON *.* to 'centreon'@'%' IDENTIFIED BY 'centreon' WITH GRANT OPTION"
mysql -h${MYSQL_HOST} -uroot -p${MYSQL_ROOT_PASSWORD} -e "GRANT ALL ON *.* to 'centreon'@'%' WITH GRANT OPTION"

sed -i "s/localhost/${MYSQL_HOST}/g" /etc/centreon/centreon.conf.php
sed -i "s/localhost/${MYSQL_HOST}/g" /etc/centreon/conf.pm
sed -i "s/localhost/${MYSQL_HOST}/g" /etc/centreon/config.d/10-database.yaml
