#!/bin/bash 

cd centreon

COMMIT=`git log -1 HEAD --pretty=format:%h`
now=`date +%s`
export RELEASE="$now.$COMMIT"

composer install --no-dev --optimize-autoloader
npm ci
find ./www/include/Administration/about -type f | xargs --delimiter='\n' sed -i -e "s/@COMMIT@/$COMMIT/g"
