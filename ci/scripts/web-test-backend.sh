#!/bin/bash 

cd centreon
mkdir build
composer install --optimize-autoloader
XDEBUG_MODE=coverage composer run-script test:ci
composer run-script codestyle:ci
#composer run-script phpstan:ci > build/phpstan.xml