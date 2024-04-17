*** Settings ***
Documentation       Start and stop gorgone

Resource            ${CURDIR}${/}..${/}..${/}resources${/}import.resource
Test Timeout        120s

*** Test Cases ***
Start and stop gorgone 
    [Arguments]    /etc/centreon-gorgone/config.yaml    /etc/centreon-gorgone/config2.yaml
    # fichier de conf : pull_central + autodiscovery
    # start gorgone 2
    FOR    ${i}    IN RANGE    5
        Start Gorgone    /etc/centreon-gorgone/config.yaml    /var/log/centreon-gorgone/gorgoned.log    info    gorgone${i}
        Sleep    5s
        Stop Gorgone    gorgone${i}
        sleep    2s
    END