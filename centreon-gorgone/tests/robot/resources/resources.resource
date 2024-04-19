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

    FOR    ${process}    IN    @{process_alias}

        ${result}    Terminate Process    ${process}
        BuiltIn.Run Keyword And Continue On Failure    Should Be True    ${result.rc} == -15 or ${result.rc} == 0    Engine badly stopped alias = ${process_alias} - code returned ${result.rc}.

    END

Gorgone Execute Sql
    [Arguments]    ${sql_file}
    ${length}    Get Length    ${sql_file}
    IF    ${length} > 0
        Connect To Database    pymysql    ${DBNAME}    ${DBUSER}    ${DBPASSWORD}    ${DBHOST}    ${DBPORT}
        Execute SQL Script    ${sql_file}
    END

Setup Gorgone Config
    [Arguments]        ${id}    @{file_list}    ${sql_file}=
    Gorgone Execute Sql    ${sql_file}

    Copy File    ${CURDIR}${/}..${/}config${/}includer.yaml    /etc/centreon-gorgone/${id}/includer.yaml

    FOR    ${file}    IN    @{file_list}
        Copy File    ${file}    /etc/centreon-gorgone/${id}/config.d/
    END

    ${result}    Run    sed -i -e 's/@UNIQIDFROMROBOTTESTINCONFIGFILE@/${id}/g' /etc/centreon-gorgone/${id}/includer.yaml
    ${result2}    Run    sed -i -e 's/@DBNAME@/${DBNAME}/g' -e 's/@DBNAME@/${DBNAME}/g' -e 's/@DBHOST@/${DBHOST}/g' -e 's/@DBPASSWORD@/${DBPASSWORD}/g' -e 's/@DBUSER@/${DBUSER}/g' -e 's/@UNIQIDFROMROBOTTESTINCONFIGFILE@/${id}/g' /etc/centreon-gorgone/${id}/config.d/*.yaml

Remove Gorgone Config
    [Arguments]        ${id}    ${sql_file}=
    Gorgone Execute Sql    ${sql_file}
    ${result}    Run    rm -rf /etc/centreon-gorgone/${id}

Check Poller Is Connected
    [Arguments]    ${port}=    ${expected_nb}=
    FOR    ${i}    IN RANGE    40
        Sleep    2
        ${nb_socket_connexion}    Run    ss -tnp | grep ':${port}' | grep ESTAB | wc -l
        Log To Console    i is : ${i}
        IF    ${expected_nb} == ${nb_socket_connexion}
          BREAK
        END
    END
    Log To Console    i is : ${i}
    Should Be True    ${i} < 39    Gorgone did not establish tcp connection in 80 seconds.

Check Poller Communicate
    [Arguments]    ${poller_id}
    ${response}     Set Variable    ${EMPTY}
    FOR    ${i}    IN RANGE    10
        Sleep    2
        ${response}=    GET  http://127.0.0.1:8085/api/internal/constatus
        Log    ${response.json()}
        IF    ${response.json()}[data][${poller_id}][last_ping_sent] > 5
            BREAK
        END
    END
    Should Be True    ${i} < 9
    Should Be True    0 == ${response.json()}[data][${poller_id}][ping_failed]    there was failed ping between the central and the poller ${poller_id}
    Should Be True    0 < ${response.json()}[data][${poller_id}][ping_ok]    there was no successful ping between the central and the poller ${poller_id}

Setup 2 Gorgone
    [Arguments]    ${communication_mode}=push
    IF    '${communication_mode}' == 'push'
        Setup Gorgone Config    gorgone_central    ${push_central_config}    sql_file=${ROOT_CONFIG}push_db_1_poller.sql
        Setup Gorgone Config    gorgone_poller_2    ${push_poller_config}
    
        Start Gorgone    /etc/centreon-gorgone/gorgone_central/includer.yaml    /etc/centreon-gorgone/gorgone_central/gorgoned.log    debug    gorgone_central
        Start Gorgone    /etc/centreon-gorgone/gorgone_poller_2/includer.yaml    /etc/centreon-gorgone/gorgone_poller_2/gorgoned.log    debug    gorgone_poller_2
    
        Check Poller Is Connected    port=5556    expected_nb=2
        Check Poller Communicate     2
    ELSE
        Fail    pull and pullwss mode are not yet implemented.
    END

