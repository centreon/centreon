# How to use toolkit to translate Centreon

## Extract centreon github sources

```SHELL
https://github.com/centreon/centreon.git
```

## Move to i18n_toolkit directory

```SHELL
cd centreon/i18n_toolkit
```

## Install dependencies

```SHELL
dnf install -y gettext php-cli
```

## Execute script

```SHELL
bash make_translation.sh <PROJECt_NAME> <lang>
```

List of projects:
- centreon

List of supported languages:
- de_DE
- es_ES
- fr_FR
- pt_BR
- pt_PT

## Translate missing strings

Using POEdit of other tools to translate *.po files fix:
- centreon/lang/`<LANG>`.UTF-8/LC_MESSAGES/messages.po
- centreon/lang/`<LANG>`.UTF-8/LC_MESSAGES/help.po

Then create a pull request to ask Centreon to merge new translation

> If you want to start another locale, you need to create a new lang directory like:
    
    ```SHELL
    mkdir centreon/lang/en_EN.UTF-8/LC_MESSAGES/
    ```
    
    Then copy messages.pot and help.pot from centreon/lang/

    ```SHELL
    cp centreon/lang/messages.pot centeron/lang/en_EN.UTF-8/LC_MESSAGES/messages.po
    cp centreon/lang/help.pot centeron/lang/en_EN.UTF-8/LC_MESSAGES/help.po
    ```

    And start translation of messages.po and help.po