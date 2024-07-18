*** Settings ***
Documentation       Start and stop gorgone

Resource            ${CURDIR}${/}..${/}..${/}resources${/}import.resource
Test Timeout        220s

*** Variables ***
@{process_list}    push_zmq_gorgone_central    push_zmq_gorgone_poller_2

*** Test Cases ***
connect 1 poller to a central
    [Teardown]    Stop Gorgone And Remove Gorgone Config    @{process_list}    sql_file=${ROOT_CONFIG}push_db_1_poller_delete.sql

    Log To Console    \nStarting the gorgone setup
    Setup Two Gorgone Instances    communication_mode=push_zmq     central_name=push_zmq_gorgone_central    poller_name=push_zmq_gorgone_poller_2
    Log To Console    End of tests.
