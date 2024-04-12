*** Settings ***
Documentation       Start and stop gorgone

Resource            ${CURDIR}${/}..${/}resources${/}import.resource

Test Timeout        120s

*** Test Cases ***
Start and stop gorgone
    Start Gorgone    /etc/centreon-gorgone/config.yaml    /var/log/centreon-gorgone/gorgoned.log    info    gorgone1
    Sleep    10s
    Stop Gorgone    gorgone1
