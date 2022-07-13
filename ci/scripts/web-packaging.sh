#!/bin/bash 

cd centreon

COMMIT=`git log -1 HEAD --pretty=format:%h`
now=`date +%s`
export RELEASE="$now.$COMMIT"

composer install --no-dev --optimize-autoloader
npm ci
npm run build
find ./www/include/Administration/about -type f | xargs --delimiter='\n' sed -i -e "s/@COMMIT@/$COMMIT/g"
mkdir -p www/locale/en_US.UTF-8/LC_MESSAGES
php bin/centreon-translations.php en lang/fr_FR.UTF-8/LC_MESSAGES/messages.po www/locale/en_US.UTF-8/LC_MESSAGES/messages.ser
for i in lang/* ; do
  localefull=`basename $i`
  langcode=`echo $localefull | cut -d _ -f 1`
  mkdir -p "www/locale/$localefull/LC_MESSAGES"
  msgfmt "lang/$localefull/LC_MESSAGES/messages.po" -o "www/locale/$localefull/LC_MESSAGES/messages.mo" || exit 1
  msgfmt "lang/$localefull/LC_MESSAGES/help.po" -o "www/locale/$localefull/LC_MESSAGES/help.mo" || exit 1
  php bin/centreon-translations.php "$langcode" "lang/$localefull/LC_MESSAGES/messages.po" "www/locale/$localefull/LC_MESSAGES/messages.ser"
done
rm -rf lang
