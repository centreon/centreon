*** Settings ***
Documentation       Centreon Gorgone for Robot Framework

Library            Process
Library            RequestsLibrary

*** Variables ***
${PERCENT}                  %
${ROOT_CONFIG}              ${CURDIR}${/}..${/}config${/}
${pull_central_config}      ${ROOT_CONFIG}pull_central_config.yaml
${pull_poller_config}       ${ROOT_CONFIG}pull_poller_config.yaml
${push_central_config}      ${ROOT_CONFIG}push_central_config.yaml
${push_poller_config}       ${ROOT_CONFIG}push_poller_config.yaml
${DBHOST}                   127.0.0.1
${DBPORT}                   3306
${DBNAME}                   centreon_gorgone_test
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
    ...    ${LOG_FILE}
    ...    --severity
    ...    ${SEVERITY}
    ...    alias=${ALIAS}

Stop Gorgone
    [Arguments]    @{process_alias}
    FOR ${process}    IN    @{process_alias}
        ${result}    Terminate Process    ${process_alias}
        Log     ${result}
        Should Be True
        ...    ${result.rc} == -15 or ${result.rc} == 0
        ...    Engine badly stopped alias = ${process_alias} - code returned ${result.rc}.
    END


Setup Gorgone Config
    [Arguments]        ${id}    @{file_list}    ${sql_file}=
   ${length}    Get Length    ${sql_file}
    IF    ${length} > 0
        Connect To Database    pymysql    ${DBNAME}    ${DBUSER}    ${DBPASSWORD}    ${DBHOST}    ${DBPORT}
        Execute SQL Script    ${sql_file}
    END
        
    Copy File    ${CURDIR}${/}..${/}config${/}includer.yaml    /etc/centreon-gorgone/${id}/includer.yaml

    FOR    ${file}    IN    @{file_list}
        Copy File    ${file}    /etc/centreon-gorgone/${id}/config.d/
    END

    ${result}    Run    sed -i -e 's/@UNIQIDFROMROBOTTESTINCONFIGFILE@/${id}/g' /etc/centreon-gorgone/${id}/includer.yaml

    ${result2}    Run    sed -i -e 's/@DBNAME@/${DBNAME}/g' -e 's/@DBNAME@/${DBNAME}/g' -e 's/@DBHOST@/${DBHOST}/g' -e 's/@DBPASSWORD@/${DBPASSWORD}/g' -e 's/@DBUSER@/${DBUSER}/g' -e 's/@UNIQIDFROMROBOTTESTINCONFIGFILE@/${id}/g' /etc/centreon-gorgone/${id}/config.d/*.yaml

Check Push Poller Communicate
    [Arguments]    ${central_id}    ${poller_id}
    ${nb_socket_connexion}    Run    ss -tnp | grep ':5556' | grep ESTAB | wc -l
     # ss see 2 connexion, one for the poller and one for the central, although it's the same connection
    Should Be Equal     2    ${nb_socket_connexion}
    ${response}=    GET  http://127.0.0.1:8085/api/internal/constatus
    Log    ${response.json()}
    Should Be Equal    0 ${response.json()}[data][2][ping_failed]



Remove Gorgone Config
    [Arguments]        ${id}    ${sql_file}=
       ${length}    Get Length    ${sql_file}
    IF    ${length} > 0
        Connect To Database    pymysql    ${DBNAME}    ${DBUSER}    ${DBPASSWORD}    ${DBHOST}    ${DBPORT}
        Execute SQL Script    ${sql_file}
    END
    ${result}    Run rm -rf /etc/centreon-gorgone/${id}