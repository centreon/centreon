*** Settings ***
Documentation       Start and stop gorgone

Resource            ${CURDIR}${/}..${/}..${/}resources${/}import.resource

Test Timeout        120s

*** Test Cases ***
Start and stop gorgone
    FOR    ${i}    IN RANGE    1
        Start Gorgone    /etc/centreon-gorgone/config.yaml    /var/log/centreon-gorgone/gorgoned.log    info    gorgone${i}
        Sleep    5s
        Stop Gorgone    gorgone${i}
        sleep    2s
    END