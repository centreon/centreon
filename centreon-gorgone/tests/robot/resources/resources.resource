*** Settings ***
Documentation       Centreon Gorgone library for Robot Framework

Library            Process
Library            RequestsLibrary

*** Variables ***
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
    [Arguments]    ${CONFIG_FILE}    ${SEVERITY}    ${ALIAS}
    ${process}    Start Process
    ...    /usr/bin/perl
    ...    /usr/bin/gorgoned
    ...    --config
    ...    ${CONFIG_FILE}
    ...    --logfile
    ...    /var/log/centreon-gorgone/${ALIAS}/gorgoned.log
    ...    --severity
    ...    ${SEVERITY}
    ...    alias=${ALIAS}

Stop Gorgone And Remove Gorgone Config
    [Documentation]    This keyword stop the gorgone process and remove the configuration in the database. file configuration are not modified as we want them in case something failed to analyse the problem
    [Arguments]    @{process_alias}    ${sql_file}=
    Gorgone Execute Sql    ${sql_file}
    # remove configuration in db if needed.

    FOR    ${process}    IN    @{process_alias}
        ${result}    Terminate Process    ${process}
        BuiltIn.Run Keyword And Continue On Failure    Should Be True    ${result.rc} == -15 or ${result.rc} == 0    Engine badly stopped alias = ${process_alias} - code returned ${result.rc}.
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
    [Arguments]        ${gorgone_name}    @{file_list}    ${sql_file}=
    Gorgone Execute Sql    ${sql_file}
    Create Directory    /var/log/centreon-gorgone/${gorgone_name}/
    Copy File    ${CURDIR}${/}..${/}config${/}includer.yaml    /etc/centreon-gorgone/${gorgone_name}/includer.yaml

    FOR    ${file}    IN    @{file_list}
        Copy File    ${file}    /etc/centreon-gorgone/${gorgone_name}/config.d/
    END
    ${key_thumbprint}    Run    perl /usr/local/bin/gorgone_key_thumbprint.pl --key-path=/var/lib/centreon-gorgone/.keys/rsakey.priv.pem | cut -d: -f4

    ${result}    Run    sed -i -e 's/@UNIQIDFROMROBOTTESTINCONFIGFILE@/${gorgone_name}/g' /etc/centreon-gorgone/${gorgone_name}/includer.yaml
    
    ${CMD}    Catenate
    ...    sed -i -e 's/@KEYTHUMBPRINT@/${key_thumbprint}/g' 
    ...    -e 's/@DBNAME@/${DBNAME}/g' 
    ...    -e 's/@DBNAME@/${DBNAME}/g' 
    ...    -e 's/@DBHOST@/${DBHOST}/g' 
    ...    -e 's/@DBPASSWORD@/${DBPASSWORD}/g' 
    ...    -e 's/@DBUSER@/${DBUSER}/g' 
    ...    -e 's/@UNIQIDFROMROBOTTESTINCONFIGFILE@/${gorgone_name}/g'
    ...    /etc/centreon-gorgone/${gorgone_name}/config.d/*.yaml

    ${result2}    Run    ${CMD}

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
    Should Be True    ${i} < 39    Gorgone did not establish tcp connection in 160 seconds.
    Log To Console    TCP connection established after ${i} attempt (4 seconds each)

Check Poller Communicate
    [Documentation]    Ask the central Gorgone rest api if it have communicated with the poller using a given ID.
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
    [Arguments]    ${communication_mode}=push_zmq    ${central_name}=gorgone_central    ${poller_name}=gorgone_poller_2

    ${result}    Run    perl /usr/local/bin/gorgone_key_generation.pl
    # generate key if there is none.
    # gorgone can generate it's own key, but as we need the thumbprint in the configuration we need to generate them before launching gorgone.
    # this script only create key if the files don't exists, and silently finish if the files already exists.
    IF    '${communication_mode}' == 'push_zmq'
        Setup Gorgone Config    ${central_name}    ${push_central_config}    sql_file=${ROOT_CONFIG}push_db_1_poller.sql
        Setup Gorgone Config    ${poller_name}    ${push_poller_config}

        Start Gorgone    /etc/centreon-gorgone/gorgone_central/includer.yaml    debug    ${central_name}
        Start Gorgone    /etc/centreon-gorgone/gorgone_poller_2/includer.yaml    debug    ${poller_name}
    
        Check Poller Is Connected    port=5556    expected_nb=2
        Check Poller Communicate     2
    ELSE
        Fail    pull and pullwss mode are not yet implemented.
    END

