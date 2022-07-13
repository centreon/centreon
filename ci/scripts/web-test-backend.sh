#!/bin/bash 

cd centreon
XDEBUG_MODE=coverage composer run-script test:ci
composer run-script codestyle:ci
composer run-script phpstan:ci > build/phpstan.xml