*** Settings ***
Documentation       Start and stop gorgone in pullwss mode

Resource            ${CURDIR}${/}..${/}..${/}resources${/}import.resource
Test Timeout        220s

*** Variables ***
@{process_list}    pullwss_gorgone_poller_2    pullwss_gorgone_central

*** Test Cases ***
check one poller can connect to a central gorgone 
    [Teardown]    Stop Gorgone And Remove Gorgone Config    @{process_list}    #sql_file=${ROOT_CONFIG}push_db_1_poller_delete.sql

    Log To Console    \nStarting the gorgone setup
    Setup Two Gorgone Instances    communication_mode=pullwss    central_name=pullwss_gorgone_central    poller_name=pullwss_gorgone_poller_2
    Log To Console    End of tests.
