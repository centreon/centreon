#!/bin/sh

su www-data -s /bin/bash -c "/tmp/install-centreon-module.php -b /usr/share/centreon/bootstrap.php -m centreon-awie"
