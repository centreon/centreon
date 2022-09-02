#!/bin/sh

set -e
set -x

service mysql start
for w in graph-monitoring host-monitoring hostgroup-monitoring httploader live-top10-cpu-usage live-top10-memory-usage service-monitoring servicegroup-monitoring tactical-overview ; do
  php /tmp/install-centreon-widget.php -b /usr/share/centreon/bootstrap.php -w $w
done
service mysql stop
