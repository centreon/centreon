*** Settings ***
Documentation       Centreon Gorgone for Robot Framework

Library             Process


*** Variables ***
${PERCENT}                  %
${pull_central_config}      ${CURDIR}${/}..${/}config${/}pull_central_config.yaml
${pull_poller_config}       ${CURDIR}${/}..${/}config${/}pull_poller_config.yaml
${push_central_config}      ${CURDIR}${/}..${/}config${/}push_central_config.yaml
${push_poller_config}       ${CURDIR}${/}..${/}config${/}push_poller_config.yaml
${DBHOST}                   127.0.0.1
${DBUSER}                   centreon
${DBPASSWORD}               password
*** Keywords ***
Start Gorgone
    [Arguments]    ${CONFIG_FILE}    ${LOG_FILE}    ${SEVERITY}    ${ALIAS}
    ${process}    Start Process
    ...    /usr/bin/perl
    ...    /usr/bin/gorgoned
    ...    --config
    ...    ${CONFIG_FILE}
    ...    --logfile
    # ...    ${LOG_FILE}
    # ...    --severity
    ...    ${SEVERITY}
    ...    alias=${ALIAS}

Stop Gorgone
    [Arguments]    ${process_alias}
    ${result}    Terminate Process    ${process_alias}
    Should Be True
    ...    ${result.rc} == -15 or ${result.rc} == 0
    ...    Engine badly stopped alias = ${process_alias} - code returned ${result.rc}.

Setup Gorgone Config
    [Arguments]    ${id}    @{file_list}

    Copy File    ${CURDIR}${/}..${/}config${/}includer.yaml    /etc/centreon-gorgone/${id}/includer.yaml

    FOR    ${file}    IN    @{file_list}
        Copy File    ${file}    /etc/centreon-gorgone/${id}/config.d/
    END

    ${result}    Run    sed -e 's/UNIQIDFROMROBOTTESTINCONFIGFILE/${id}/g' /etc/centreon-gorgone/${id}/includer.yaml

    ${result2}    Run    sed -i -e 's/@DBHOST@/${DBHOST}/g' -e 's/@DBPASSWORD@/${DBPASSWORD}/g' -e 's/@DBUSER@/${DBUSER}/g' /etc/centreon-gorgone/${id}/config.d/push_central_config.yaml



