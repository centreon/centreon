*** Settings ***
Documentation       Start and stop gorgone

Resource            ${CURDIR}${/}..${/}..${/}..${/}resources${/}import.resource

Test Timeout        220s
Test Teardown    Stop Gorgone    @{process_list}

*** Variables ***
@{process_list}    gorgone_central    gorgone_poller_2


*** Test Cases ***
test Evan

    Setup 2 Gorgone    communication_mode=push

    Log To Console    removing gorgone config here.
    Remove Gorgone Config    gorgone_central   sql_file=${ROOT_CONFIG}push_db_1_poller.sql
    Remove Gorgone Config    gorgone_poller
    Log To Console    End of tests.

    # do something
