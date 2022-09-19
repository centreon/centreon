#!/bin/bash
set -ex

cd centreon
composer install
cd ..
ln -s ./centreon/vendor ./vendor
./centreon/vendor/bin/phpstan analyse -c ./centreon/phpstan.neon --level 6 --memory-limit=512M --error-format=checkstyle --no-interaction --no-progress $1 $2 > checkstyle-phpstan.xml