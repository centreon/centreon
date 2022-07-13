#!/bin/bash 

cd centreon
mkdir build
composer install --optimize-autoloader
XDEBUG_MODE=coverage composer run-script test:ci
phpcs --config-set ignore_warnings_on_exit 1
composer run-script codestyle:ci
#composer run-script phpstan:ci > build/phpstan.xml