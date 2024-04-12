*** Settings ***
Documentation       Centreon Gorgone for Robot Framework
Library             Process

*** Variables ***
${PERCENT}                  %

*** Keywords ***
Start Gorgone
    [Arguments]    ${CONFIG_FILE}    ${LOG_FILE}    ${SEVERITY}    ${ALIAS}
    ${process}    Start Process
    ...    /usr/bin/perl
    ...    /usr/bin/gorgoned
    ...    --config
    ...    ${CONFIG_FILE}
    ...    --logfile
    ...    ${LOG_FILE}
    ...    --severity
    ...    ${SEVERITY}
    ...    alias=${ALIAS}

Stop Gorgone
    [Arguments]    ${process_alias}
    ${result}    Terminate Process    ${process_alias}
    Should Be True
    ...    ${result.rc} == -15 or ${result.rc} == 0
    ...    Engine badly stopped alias = ${process_alias} - code returned ${result.rc}.