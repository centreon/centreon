#!/bin/sh

mkdir -p /etc/my.cnf.d
# In HTTPS mode, 04-tls.sh installs a [client] config with ssl-ca pointing at our
# Root CA; emitting skip-ssl here would override it (conf.d is read alphabetically
# and client-no-ssl.cnf sorts after centreon-tls-client.cnf). Gate on cert
# presence, and drop any stale file from a prior HTTP run on container restart.
if [ -f /etc/pki/centreon-tls/rootCA.pem ]; then
  rm -f /etc/my.cnf.d/client-no-ssl.cnf
else
  printf '[client]\nskip-ssl\n' > /etc/my.cnf.d/client-no-ssl.cnf
fi

# Wait for the database to be up and running.
echo "Waiting for DB server to be ready..."
while true ; do
  if mysqladmin -h"${MYSQL_HOST}" -uroot -p"${MYSQL_ROOT_PASSWORD}" ping --connect-timeout=5 ; then
    echo "DB server is running."
    break
  fi
  sleep 1
done
