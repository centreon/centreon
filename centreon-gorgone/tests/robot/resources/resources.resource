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

Stop Gorgone And Remove Gorgone Config
    [Arguments]    @{process_alias}    ${sql_file}=
    Gorgone Execute Sql    ${sql_file}
    # remove configuration in db if needed.

    FOR    ${process}    IN    @{process_alias}
        ${result}    Terminate Process    ${process}
        BuiltIn.Run Keyword And Continue On Failure    Should Be True    ${result.rc} == -15 or ${result.rc} == 0    Engine badly stopped alias = ${process_alias} - code returned ${result.rc}.
        ${result}    Run    rm -rf /etc/centreon-gorgone/${process}
    END

Gorgone Execute Sql
    [Arguments]    ${sql_file}
    ${length}    Get Length    ${sql_file}
    IF    ${length} > 0
        Connect To Database    pymysql    ${DBNAME}    ${DBUSER}    ${DBPASSWORD}    ${DBHOST}    ${DBPORT}
        Log To Console    Executing sql file ${sql_file}
        Execute SQL Script    ${sql_file}
    END

Setup Gorgone Config
    [Arguments]        ${id}    @{file_list}    ${sql_file}=
    Gorgone Execute Sql    ${sql_file}

    Copy File    ${CURDIR}${/}..${/}config${/}includer.yaml    /etc/centreon-gorgone/${id}/includer.yaml

    FOR    ${file}    IN    @{file_list}
        Copy File    ${file}    /etc/centreon-gorgone/${id}/config.d/
    END
    ${key_thumbprint}    Run    perl /usr/local/bin/gorgone_key_thumbprint.pl --key-path=/var/lib/centreon-gorgone/.keys/rsakey.priv.pem | cut -d: -f4

    ${result}    Run    sed -i -e 's/@UNIQIDFROMROBOTTESTINCONFIGFILE@/${id}/g' /etc/centreon-gorgone/${id}/includer.yaml
    ${result2}    Run    sed -i -e 's/@KEYTHUMBPRINT@/${key_thumbprint}/g' -e 's/@DBNAME@/${DBNAME}/g' -e 's/@DBNAME@/${DBNAME}/g' -e 's/@DBHOST@/${DBHOST}/g' -e 's/@DBPASSWORD@/${DBPASSWORD}/g' -e 's/@DBUSER@/${DBUSER}/g' -e 's/@UNIQIDFROMROBOTTESTINCONFIGFILE@/${id}/g' /etc/centreon-gorgone/${id}/config.d/*.yaml

Check Poller Is Connected
    [Arguments]    ${port}=    ${expected_nb}=
    Log To Console    checking TCP connection is established...
    FOR    ${i}    IN RANGE    40
        Sleep    4
        ${nb_socket_connexion}    Run    ss -tnp | grep ':${port}' | grep ESTAB | wc -l
        IF    ${expected_nb} == ${nb_socket_connexion}
          BREAK
        END
    END
    Log To Console    TCP connection establishing after ${i} attempt
    Should Be True    ${i} < 39    Gorgone did not establish tcp connection in 80 seconds.
    Log To Console    TCP connection established after ${i} attempt (4 seconds each)

Check Poller Communicate
    [Arguments]    ${poller_id}
    ${response}     Set Variable    ${EMPTY}
    Log To Console    checking Gorgone see poller in rest api response...
    FOR    ${i}    IN RANGE    10
        Sleep    5
        ${response}=    GET  http://127.0.0.1:8085/api/internal/constatus
        Log    ${response.json()}
        IF    ${response.json()}[data][${poller_id}][ping_failed] > 0 or ${response.json()}[data][${poller_id}][ping_ok] > 0
            BREAK
        END
    END
    Log To Console    json response : ${response.json()}
    Should Be True    ${i} < 9    timeout waiting for poller status in gorgone rest api (/api/internal/constatus) : ${response.json()}
    Should Be True    0 == ${response.json()}[data][${poller_id}][ping_failed]    there was failed ping between the central and the poller ${poller_id}
    Should Be True    0 < ${response.json()}[data][${poller_id}][ping_ok]    there was no successful ping between the central and the poller ${poller_id}
    

Setup 2 Gorgone
    [Arguments]    ${communication_mode}=push    ${central_name}=gorgone_central    ${poller_name}=gorgone_poller_2
    IF    '${communication_mode}' == 'push'
        Setup Gorgone Config    ${central_name}    ${push_central_config}    sql_file=${ROOT_CONFIG}push_db_1_poller.sql
        Setup Gorgone Config    ${poller_name}    ${push_poller_config}
        Create Directory    /var/log/centreon-gorgone/${central_name}/
        Create Directory    /var/log/centreon-gorgone/${poller_name}/

        Start Gorgone    /etc/centreon-gorgone/gorgone_central/includer.yaml    /var/log/centreon-gorgone/${central_name}/gorgoned.log    debug    ${central_name}
        Start Gorgone    /etc/centreon-gorgone/gorgone_poller_2/includer.yaml    /var/log/centreon-gorgone/${poller_name}/gorgoned.log    debug    ${poller_name}
    
        Check Poller Is Connected    port=5556    expected_nb=2
        Check Poller Communicate     2
    ELSE
        Fail    pull and pullwss mode are not yet implemented.
    END

