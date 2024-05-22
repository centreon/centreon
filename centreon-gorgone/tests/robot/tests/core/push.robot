*** Settings ***
Documentation       Start and stop gorgone

Resource            ${CURDIR}${/}..${/}..${/}resources${/}import.resource
Test Timeout        220s

*** Variables ***
@{process_list}    gorgone_central    gorgone_poller_2

*** Test Cases ***
test Evan
    [Teardown]    Stop Gorgone And Remove Gorgone Config    @{process_list}    sql_file=${ROOT_CONFIG}push_db_1_poller_delete.sql

    Log To Console    \nStarting the gorgone setup
    Setup Two Gorgone Instances    push_zmq
    Log To Console    End of tests.
