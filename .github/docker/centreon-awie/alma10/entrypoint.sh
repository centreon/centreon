#!/bin/sh

su apache -s /bin/bash -c "/tmp/install-centreon-module.php -b /usr/share/centreon/bootstrap.php -m centreon-awie"
