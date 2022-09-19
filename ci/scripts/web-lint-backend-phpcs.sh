#!/bin/bash
set -ex

cd centreon
composer install
cd ..
./centreon/vendor/bin/phpcs --extensions=php --standard=./centreon/ruleset.xml --report=checkstyle --report-file=./checkstyle-phpcs.xml $1 $2