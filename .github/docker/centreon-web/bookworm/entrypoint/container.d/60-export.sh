#!/bin/sh

su www-data -s /bin/bash -c "php /usr/share/centreon/bin/centreon -u admin -p 'Centreon2025!' -a POLLERGENERATE -v 1"
su www-data -s /bin/bash -c "php /usr/share/centreon/bin/centreon -u admin -p 'Centreon2025!' -a CFGMOVE -v 1"
su www-data -s /bin/bash -c "php /usr/share/centreon/bin/centreon -u admin -p 'Centreon2025!' -a POLLERRESTART -v 1"

