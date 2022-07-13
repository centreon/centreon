#!/bin/bash 

cd centreon
mkdir build
XDEBUG_MODE=coverage composer run-script test:ci
composer run-script codestyle:ci
#composer run-script phpstan:ci > build/phpstan.xml