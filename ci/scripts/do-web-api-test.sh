#!/bin/sh

# Prepare Behat.yml
cd /tmp/centreon
alreadyset=`grep docker-compose-web.yml < tests/api/behat.yml || true`
if [ -z "$alreadyset" ] ; then
  sed -i 's#    Centreon\\Test\\Behat\\Extensions\\ContainerExtension:#    Centreon\\Test\\Behat\\Extensions\\ContainerExtension:\n      log_directory: ../api-integration-test-logs\n      web: docker-compose-web.yml#g' tests/api/behat.yml
fi

# ignore php 8 requirement in composer.json
composer self-update
composer self-update --2
composer dump-autoload --ignore-platform-reqs

# Run acceptance tests.
rm -rf ../xunit-reports
mkdir ../xunit-reports
rm -rf ../api-integration-test-logs
mkdir ../api-integration-test-logs
./vendor/bin/behat --config tests/api/behat.yml --format=pretty --out=std --format=junit --out="../xunit-reports" "$2"
