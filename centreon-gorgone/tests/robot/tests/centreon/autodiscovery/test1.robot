*** Settings ***
Documentation       Start and stop gorgone

Resource            ${CURDIR}${/}..${/}..${/}..${/}resources${/}import.resource

Test Timeout        220s
Test Teardown    Stop Gorgone    gorgone_central    gorgone_poller

*** Test Cases ***
test Evan
    Setup Gorgone Config    gorgone_central    ${push_central_config}    sql_file=${ROOT_CONFIG}push_db_1_poller.sql
    Setup Gorgone Config    gorgone_poller    ${push_poller_config}

    Start Gorgone    /etc/centreon-gorgone/gorgone_central/includer.yaml    /var/log/centreon-gorgone/robotgorgonecentral.log    debug    gorgone_central
    Start Gorgone    /etc/centreon-gorgone/gorgone_poller/includer.yaml    /var/log/centreon-gorgone/robotgorgonepoller.log    debug    gorgone_poller

    Sleep    8s
    Check Push Poller Communicate     gorgone_central    gorgone_poller

    #Stop Gorgone    gorgone_central
    #Stop Gorgone    gorgone_poller
    #Remove Gorgone Config    gorgone_central   sql_file=${ROOT_CONFIG}push_db_1_poller.sql
    # do something
