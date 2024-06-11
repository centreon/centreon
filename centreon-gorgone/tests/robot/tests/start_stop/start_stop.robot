*** Settings ***
Documentation       Start and stop gorgone

Resource            ${CURDIR}${/}..${/}..${/}resources${/}import.resource
Test Timeout        120s

*** Test Cases ***
Start and stop gorgone
    # fichier de conf : pull_central + autodiscovery
    # start gorgone 2
    FOR    ${i}    IN RANGE    5
        Setup Gorgone Config    gorgone_start_stop${i}    ${CURDIR}${/}config.yaml
        Log To Console    Starting Gorgone...
        Start Gorgone    /etc/centreon-gorgone/gorgone_start_stop${i}/includer.yaml    debug    gorgone_start_stop${i}
        Sleep    5s
        Log To Console    Stopping Gorgone...
        Stop Gorgone And Remove Gorgone Config    gorgone_start_stop${i}
        sleep    2s
    END
