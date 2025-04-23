#!/bin/bash

#
# Check working directory
#
BASE_DIR=$(dirname $0)
BASE_DIR=$( cd $BASE_DIR; pwd )
export BASE_DIR

#
# Check external tools
#
PHP=$(which php)
if [ -z $PHP ] ; then
    echo -e "You must install php-cli before continue"
    exit 1
fi

XGETTEXT=$(/usr/bin/which xgettext)
if [ -z $XGETTEXT ] ; then
    echo -e "You must install xgettext before continue"
    exit 1
fi

MSGMERGE=$(/usr/bin/which msgmerge)
if [ -z $MSGMERGE ] ; then
    echo -e "You must install msgmerge before continue"
    exit 1
fi

# Define projects
PROJECTS=(
    "centreon"
    "centreon-license-manager"
    "centreon-autodiscovery"
    "centreon-pp-manager"
    "centreon-bam"
    "centreon-map"
    "centreon-mbi"
)

# Define language
LANGS=(
    "de_DE"
    "es_ES"
    "fr_FR"
    "pt_BR"
    "pt_PT"
)

#
# Define global vars
#
PO_SRC=$BASE_DIR/po_src
CAN_BE_TRANSLATE=false

# Check project name
if [ "$1" ]; then
    for PROJECT in ${PROJECTS[@]};
    do
        if [ "$1" = "$PROJECT" ]; then
            CAN_BE_TRANSLATE=true
            PROJECT=$1
            break
        fi
    done
    if [ $CAN_BE_TRANSLATE = false ]; then
        echo -e "Project $1 can't be translated"
        exit 1
    fi
else
    echo -e "Please execute following command: $0 <project name> <locale>"
    exit 1
fi

# Check for locale
CAN_BE_TRANSLATE=false

if [ "$2" ]; then
    for LANG in ${LANGS[@]};
    do
        if [ "$2" = "$LANG" ]; then
            CAN_BE_TRANSLATE=true
            LANG=$2
            LC_ALL=$LANG.UTF-8
            export LC_ALL
            break
        fi
    done
    if [ $CAN_BE_TRANSLATE = false ]; then
        echo -e "Project $1 can't be translated in $2 lcoale"
        exit 1
    fi
else
    echo -e "Please execute following command: $0 <project name> <locale>"
    exit 1
fi

BASE_DIR_PROJECT="$BASE_DIR/../../../$PROJECT"

if [ "$PROJECT" = "centreon" ]; then
    echo -n "Extracting strings to translate the menus"
    $PHP $BASE_DIR/parseSQLDatabaseConfigurationForTranslation.php $BASE_DIR_PROJECT/www/install/insertTopology.sql > $BASE_DIR_PROJECT/www/install/menu_translation.php
    echo -n " - 0K"
    echo
    echo -n "Extracting strings to translate from Centreon Broker forms"
    $PHP $BASE_DIR/parseSQLDatabaseConfigurationForTranslation.php $BASE_DIR_PROJECT/www/install/insertBaseConf.sql > $BASE_DIR_PROJECT/www/install/centreon_broker_translation.php
    echo -n " - 0K"
    echo
    echo -n "Extracting strings to translate from legacy pages"
    $PHP $BASE_DIR/tsmarty2centreon.php $BASE_DIR_PROJECT > $BASE_DIR_PROJECT/www/install/smarty_translate.php
    echo -n " - 0K"
    echo
    echo -n "Extracting strings to translate from ReactJS pages"
    $PHP $BASE_DIR/reachjs2centreon.php $BASE_DIR_PROJECT > $BASE_DIR_PROJECT/www/install/front_translate.php
    echo -n " - 0K"
    echo
    echo -n "Extracting strings to translate from Dashboard widgets"
    $PHP $BASE_DIR/parseDashboardWidgets.php $BASE_DIR_PROJECT > $BASE_DIR_PROJECT/www/install/dashboard_widgets.php
    echo -n " - 0K"
    echo

    echo -e ""
    echo -n "List all PHP files excluding help.php files"
    find $BASE_DIR_PROJECT -name '*.php' | grep -v "help" > $PO_SRC
    echo -n " - 0K"
    echo
    echo -n "Generate messages.pot file including all strings to translate"
    $XGETTEXT --from-code=UTF-8 --default-domain=messages -k_ --files-from=$PO_SRC --output=$BASE_DIR_PROJECT/lang/messages.pot > /dev/null 2>&1
    # remove absolute path from comments
    sed -i -r 's/#:.+\.\.\//#: /g' $BASE_DIR_PROJECT/lang/messages.pot
    # remove line number from comments
    sed -i -r 's/:[0-9]+$//g' $BASE_DIR_PROJECT/lang/messages.pot
    COUNT_ADDED_LINES=$(git diff --numstat | grep "$BASE_DIR_PROJECT/lang/messages.pot" | awk '{print $1;}')
    COUNT_REMOVED_LINES=$(git diff --numstat | grep "$BASE_DIR_PROJECT/lang/messages.pot" | awk '{print $2;}')
    git diff --numstat | grep "lang/messages.pot"
    echo -n "::warning::Added lines: $COUNT_ADDED_LINES, Removed lines: $COUNT_REMOVED_LINES"
    if [[ "$COUNT_ADDED_LINES" == "1" && $COUNT_REMOVED_LINES == "1" ]]; then
        git checkout $BASE_DIR_PROJECT/lang/messages.pot
    fi
    echo -n " - 0K"
    echo

    rm -f $BASE_DIR_PROJECT/www/install/menu_translation.php
    rm -f $BASE_DIR_PROJECT/www/install/centreon_broker_translation.php
    rm -f $BASE_DIR_PROJECT/www/install/smarty_translate.php
    rm -f $BASE_DIR_PROJECT/www/install/front_translate.php
    rm -f $BASE_DIR_PROJECT/www/install/dashboard_widgets.php

    # Merge existing translation file with new POT file
    $MSGMERGE $BASE_DIR_PROJECT/lang/$LANG.UTF-8/LC_MESSAGES/messages.po $BASE_DIR_PROJECT/lang/messages.pot -o $BASE_DIR_PROJECT/lang/$LANG.UTF-8/LC_MESSAGES/messages_new.po
    mv -f $BASE_DIR_PROJECT/lang/$LANG.UTF-8/LC_MESSAGES/messages_new.po $BASE_DIR_PROJECT/lang/$LANG.UTF-8/LC_MESSAGES/messages.po

    missing_translation=$(msggrep -v -T -e "." $BASE_DIR_PROJECT/lang/$LANG.UTF-8/LC_MESSAGES/messages.po | grep -c ^msgstr)
    echo -e "Warning: Missing $missing_translation strings to translate from $PROJECT/lang/$LANG.UTF-8/LC_MESSAGES/messages.po"

    echo -e ""
    echo -n "List all help.php files"
    find $BASE_DIR_PROJECT/www -name 'help.php' > $PO_SRC
    echo -n " - 0K"
    echo
    echo -n "Generate help.pot file including all strings to translate"
    $XGETTEXT --from-code=UTF-8 --default-domain=messages -k_ --files-from=$PO_SRC --output=$BASE_DIR_PROJECT/lang/help.pot > /dev/null 2>&1
    # remove absolute path from comments
    sed -i -r 's/#:.+\.\.\//#: /g' $BASE_DIR_PROJECT/lang/help.pot
    # remove line number from comments
    sed -i -r 's/:[0-9]+$//g' $BASE_DIR_PROJECT/lang/help.pot
    COUNT_ADDED_LINES=$(git diff --numstat | grep "$BASE_DIR_PROJECT/lang/help.pot" | awk '{print $1;}')
    COUNT_REMOVED_LINES=$(git diff --numstat | grep "$BASE_DIR_PROJECT/lang/help.pot" | awk '{print $2;}')
    git diff --numstat | grep "lang/help.pot"
    echo -n "::warning::Added lines: $COUNT_ADDED_LINES, Removed lines: $COUNT_REMOVED_LINES"
    if [[ "$COUNT_ADDED_LINES" == "1" && $COUNT_REMOVED_LINES == "1" ]]; then
        git checkout $BASE_DIR_PROJECT/lang/help.pot
    fi
    echo -n " - 0K"
    echo

    # Merge existing translation file with new POT file
    $MSGMERGE $BASE_DIR_PROJECT/lang/$LANG.UTF-8/LC_MESSAGES/help.po $BASE_DIR_PROJECT/lang/help.pot -o $BASE_DIR_PROJECT/lang/$LANG.UTF-8/LC_MESSAGES/help_new.po
    mv -f $BASE_DIR_PROJECT/lang/$LANG.UTF-8/LC_MESSAGES/help_new.po $BASE_DIR_PROJECT/lang/$LANG.UTF-8/LC_MESSAGES/help.po

    missing_translation=$(msggrep -v -T -e "." $BASE_DIR_PROJECT/lang/$LANG.UTF-8/LC_MESSAGES/help.po | grep -c ^msgstr)
    echo -e "Warning: Missing $missing_translation strings to translate from $PROJECT/lang/$LANG.UTF-8/LC_MESSAGES/help.po"
fi
if [ "$PROJECT" = "centreon-bam" ]; then
    echo -n "Extracting strings to translate the menus"
    $PHP $BASE_DIR/parseSQLDatabaseConfigurationForTranslation.php $BASE_DIR_PROJECT/www/modules/centreon-bam-server/sql/install.sql > $BASE_DIR_PROJECT/www/modules/centreon-bam-server/menu_translation.php
    echo -n " - 0K"
    echo
    echo -n "Extracting strings to translate from legacy pages"
    $PHP $BASE_DIR/tsmarty2centreon.php $BASE_DIR_PROJECT/www/modules/centreon-bam-server > $BASE_DIR_PROJECT/www/modules/centreon-bam-server/smarty_translate.php
    echo -n " - 0K"
    echo
    echo -n "Extracting strings to translate from ReactJS pages"
    $PHP $BASE_DIR/reachjs2centreon.php $BASE_DIR_PROJECT > $BASE_DIR_PROJECT/www/modules/centreon-bam-server/front_translate.php
    echo -n " - 0K"
    echo

    echo -e ""
    echo -n "List all PHP files excluding help.php files"
    find $BASE_DIR_PROJECT -name '*.php' | egrep -v "(help|feature|test)" > $PO_SRC
    echo -n " - 0K"
    echo
    echo -n "Generate messages.pot file including all strings to translate"
    $XGETTEXT --from-code=UTF-8 --default-domain=messages -k_ --files-from=$PO_SRC --output=$BASE_DIR_PROJECT/www/modules/centreon-bam-server/locale/messages.pot > /dev/null 2>&1
    echo -n " - 0K"
    echo

    echo
    echo -n "Remove translation already present in Centreon: "
    $PHP translationDuplicationRemoval.php $BASE_DIR $PROJECT
    echo

    if [ -f "$BASE_DIR_PROJECT/www/modules/centreon-bam-server/locale/$LANG.UTF-8/LC_MESSAGES/messages.po" ]; then
        # Merge existing translation file with new POT file
        $MSGMERGE $BASE_DIR_PROJECT/www/modules/centreon-bam-server/locale/$LANG.UTF-8/LC_MESSAGES/messages.po $BASE_DIR_PROJECT/www/modules/centreon-bam-server/locale/messages.pot -o $BASE_DIR_PROJECT/www/modules/centreon-bam-server/locale/$LANG.UTF-8/LC_MESSAGES/messages_new.po
        mv -f $BASE_DIR_PROJECT/www/modules/centreon-bam-server/locale/$LANG.UTF-8/LC_MESSAGES/messages_new.po $BASE_DIR_PROJECT/www/modules/centreon-bam-server/locale/$LANG.UTF-8/LC_MESSAGES/messages.po

        missing_translation=$(msggrep -v -T -e "." $BASE_DIR_PROJECT/www/modules/centreon-bam-server/locale/$LANG.UTF-8/LC_MESSAGES/messages.po | grep -c ^msgstr)
        echo -e "Missing $missing_translation strings to translate from $PROJECT/www/modules/centreon-bam-server/locale/$LANG.UTF-8/LC_MESSAGES/messages.po"
    fi

    echo -e ""
    echo -n "List all help.php files"
    find $BASE_DIR_PROJECT/www -name 'help.php' > $PO_SRC
    echo -n " - 0K"
    echo
    echo -n "Generate help.pot file including all strings to translate"
    $XGETTEXT --from-code=UTF-8 --default-domain=messages -k_ --files-from=$PO_SRC --output=$BASE_DIR_PROJECT/www/modules/centreon-bam-server/locale/help.pot > /dev/null 2>&1
    echo -n " - 0K"
    echo

    if [ -f "$BASE_DIR_PROJECT/www/modules/centreon-bam-server/locale/$LANG.UTF-8/LC_MESSAGES/help.po" ]; then
        # Merge existing translation file with new POT file
        $MSGMERGE $BASE_DIR_PROJECT/www/modules/centreon-bam-server/locale/$LANG.UTF-8/LC_MESSAGES/help.po $BASE_DIR_PROJECT/www/modules/centreon-bam-server/locale/help.pot -o $BASE_DIR_PROJECT/www/modules/centreon-bam-server/locale/$LANG.UTF-8/LC_MESSAGES/help_new.po
        mv -f $BASE_DIR_PROJECT/www/modules/centreon-bam-server/locale/$LANG.UTF-8/LC_MESSAGES/help_new.po $BASE_DIR_PROJECT/www/modules/centreon-bam-server/locale/$LANG.UTF-8/LC_MESSAGES/help.po

        missing_translation=$(msggrep -v -T -e "." $BASE_DIR_PROJECT/www/modules/centreon-bam-server/locale/$LANG.UTF-8/LC_MESSAGES/help.po | grep -c ^msgstr)
        echo -e "Missing $missing_translation strings to translate from $PROJECT/www/modules/centreon-bam-server/locale/$LANG.UTF-8/LC_MESSAGES/help.po"
    fi
    if [ -f "$BASE_DIR_PROJECT/www/modules/centreon-bam-server/menu_translation.php" ]; then
        echo -n "Removing temporary files"
        rm $BASE_DIR_PROJECT/www/modules/centreon-bam-server/menu_translation.php -f >> /dev/null 2>&1
        echo -n " - 0K"
        echo
    fi
fi

rm -f $PO_SRC
