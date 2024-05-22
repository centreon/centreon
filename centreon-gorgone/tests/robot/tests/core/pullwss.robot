*** Settings ***
Documentation       Start and stop gorgone in pullwss mode

Resource            ${CURDIR}${/}..${/}..${/}resources${/}import.resource
Test Timeout        220s

*** Test Cases ***
check one poller can connect to a central and gorgone central stop first
    [Teardown]    Stop Gorgone And Remove Gorgone Config    @{process_list}
    @{process_list}    Set Variable    pullwss_gorgone_central    pullwss_gorgone_poller_2
    Log To Console    \nStarting the gorgone setup
    Setup Two Gorgone Instances    communication_mode=pullwss    central_name=pullwss_gorgone_central    poller_name=pullwss_gorgone_poller_2
    Ctn Check No Error In Logs    pullwss_gorgone_poller_2
    Log To Console    End of tests.

check one poller can connect to a central and gorgone poller stop first
    [Teardown]    Stop Gorgone And Remove Gorgone Config    @{process_list}
    @{process_list}    Set Variable    pullwss_gorgone_poller_2    pullwss_gorgone_central
    Log To Console    \nStarting the gorgone setup

    Setup Two Gorgone Instances    communication_mode=pullwss    central_name=pullwss_gorgone_central    poller_name=pullwss_gorgone_poller_2
    Ctn Check No Error In Logs    pullwss_gorgone_poller_2
    Log To Console    End of tests.
