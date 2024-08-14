*** Settings ***
Documentation       Start and stop gorgone

Resource            ${CURDIR}${/}..${/}..${/}resources${/}import.resource
Test Timeout        220s

*** Variables ***
@{process_list}    push_zmq_gorgone_central    push_zmq_gorgone_poller_2

*** Test Cases ***
connect 1 poller to a central
    [Teardown]    Stop Gorgone And Remove Gorgone Config    @{process_list}    sql_file=${ROOT_CONFIG}push_db_1_poller_delete.sql

    Log To Console    \nStarting the gorgone setup
    Setup Two Gorgone Instances    communication_mode=push_zmq     central_name=push_zmq_gorgone_central    poller_name=push_zmq_gorgone_poller_2
    # Test
    Log To Console    End of tests.

check central don't eat cpu when poller is not connected
    [Tags]    long_tests    MON-130747
    ${central_name}=    Set Variable    push_zmq_gorgone_central
    [Teardown]    Stop Gorgone And Remove Gorgone Config    push_zmq_gorgone_central    sql_file=${ROOT_CONFIG}push_db_1_poller_delete.sql

    @{central_push_config}=    Create List    ${push_central_config}    ${gorgone_core_config}
    Setup Gorgone Config    ${central_push_config}    gorgone_name=${central_name}    sql_file=${ROOT_CONFIG}push_db_1_poller.sql
    Start Gorgone    debug    ${central_name}
    Wait Until Port Is Bind    8085
    Ctn Wait Until Poller Fail To Connect    1
    Ctn Check Cpu Until Timeout    

    
*** Keywords ***
Ctn Check Cpu Until Timeout
    [Arguments]    ${timeout}=60s    ${process_whitelist}=gorgone-proxy    ${max_cpu_usage}=40
    ${max_date}    Get Current Date    increment=${timeout}
    ${current_date}    Get Current Date

    WHILE    '${max_date}' > '${current_date}'
        ${cpu_conso}    Run    echo $(( $(ps -eo cp,args:100 | grep -v grep | grep -i ${process_whitelist} | awk '{print $1}' | paste -sd+) ))
        Should Be True    ${cpu_conso} < ${max_cpu_usage}    Gorgone consume too much cpu : ${cpu_conso}
        ${current_date}    Get Current Date
        Sleep    2
    END
    
    
Ctn Wait Until Poller Fail To Connect
    [Arguments]    ${nb_fail}=1    ${poller_id}=2

    ${response}     Set Variable    ${EMPTY}
    FOR    ${i}    IN RANGE    35
        Sleep    5
        ${response}=    GET  http://127.0.0.1:8085/api/internal/constatus
        Log    ${response.json()}
        IF    not ${response.json()}[data]
            CONTINUE
        END
        IF    ${response.json()}[data][${poller_id}][ping_failed] >= ${nb_fail} or ${response.json()}[data][${poller_id}][ping_ok] > 0
            BREAK
        END
    END
    Log To Console    json response : ${response.json()}
    Should Be True    ${i} < 34    timeout after ${i} time waiting for poller status in gorgone rest api (/api/internal/constatus)
    Should Be True    ${nb_fail} == ${response.json()}[data][${poller_id}][ping_failed]    there was failed ping between the central and the poller ${poller_id}
    Should Be True    0 == ${response.json()}[data][${poller_id}][ping_ok]    there was successful ping between the central and the poller ${poller_id}
    Log To Console    ${nb_fail} failed ping between the central and the poller ${poller_id}
