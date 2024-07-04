*** Settings ***
Documentation       Start and stop Gorgone with pull configuration

Resource            ${CURDIR}${/}..${/}..${/}resources${/}import.resource
Test Timeout        300s

*** Variables ***
@{process_list}    pull_gorgone_central    pull_gorgone_poller_2

*** Test Cases ***
connect 1 poller to a central with pull configuration
    [Teardown]    Stop Gorgone And Remove Gorgone Config    @{process_list}    sql_file=${ROOT_CONFIG}push_db_1_poller_delete.sql

    Log To Console    \nStarting the Gorgone setup with pull configuration
    Setup Two Gorgone Instances    communication_mode=pull    central_name=pull_gorgone_central    poller_name=pull_gorgone_poller_2
    Log To Console    End of tests.
