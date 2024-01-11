#!/bin/sh

# Wait for the database to be up and running.
while true ; do
  timeout 20 mysql -h${MYSQL_HOST} -uroot -p${MYSQL_ROOT_PASSWORD} -e 'SELECT User FROM user' mysql
  retval=$?
  if [ "$retval" = 0 ] ; then
    echo 'DB server is running.'
    break ;
  else
    echo 'DB server is not yet responding.'
    sleep 1
  fi
done

DATABASES_DUMP_DIR="/usr/local/src/sql/databases"
for file in `ls $DATABASES_DUMP_DIR` ; do
  myloader -h ${MYSQL_HOST} -u root -p ${MYSQL_ROOT_PASSWORD} -P 3306 -s $file -o -d $DATABASES_DUMP_DIR/$file
done

if [ $CENTREON_DATASET = "1" ]; then
  DATA_DUMP_DIR="/usr/local/src/sql/data"
  for file in `ls $DATA_DUMP_DIR` ; do
    mysql -h${MYSQL_HOST} -uroot -p${MYSQL_ROOT_PASSWORD} centreon < $DATA_DUMP_DIR/$file
  done
fi

mysql -h${MYSQL_HOST} -uroot -p${MYSQL_ROOT_PASSWORD} centreon -e "UPDATE cfg_centreonbroker_info SET config_value = '${MYSQL_HOST}' WHERE config_key = 'db_host'"

mysql -h${MYSQL_HOST} -uroot -p${MYSQL_ROOT_PASSWORD} -e "GRANT ALL ON *.* to 'centreon'@'%' WITH GRANT OPTION"

sed -i "s/localhost/${MYSQL_HOST}/g" /etc/centreon/centreon.conf.php
sed -i "s/localhost/${MYSQL_HOST}/g" /etc/centreon/conf.pm
sed -i "s/localhost/${MYSQL_HOST}/g" /etc/centreon/config.d/10-database.yaml

