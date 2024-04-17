*** Settings ***
Documentation       Start and stop gorgone

Resource            ${CURDIR}${/}..${/}..${/}..${/}resources${/}import.resource

Test Timeout        120s


*** Test Cases ***
test Evan
    Setup Gorgone Config    123456    ${push_central_config}
