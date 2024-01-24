
*** Settings ***
Documentation     centreon gorgone auto discovery tests require  pip install robotframework-databaselibrary
Library            Process
Library            DateTime
Library            Examples
Library            OperatingSystem
Library            RequestsLibrary
Library            gorgone-api.py
Library             DatabaseLibrary
Resource            ./db_configuration.resource
Suite Setup    Connect To Database    pymysql    ${DBName}    ${DBUser}    ${DBPass}    ${DBHost}    ${DBPort}
Suite Teardown    Disconnect From Database

*** Test Cases ***
#start gorgone
#    [Documentation]    Start-Stop Gorgone
#    [Tags]    gorgone    start-stop
#    Set Log Level    TRACE
#
#    ${result}    Run Process    sudo systemctl start gorgoned    shell=True
#
#    ${result}    Run Process    sudo systemctl is-active gorgoned    shell=True
#    Log    ${result.stdout}
#    Should Match Regexp   ${result.stdout}    ^active$
launch auto discovery on all rules
    [Documentation]    launch gorgone autodisco
    [Tags]    gorgone

    FOR    ${index}    IN RANGE    0    50
        prepare db    ${index}
    END
    &{data}=    Create dictionary
    ${launchDiscoveryResponse}=    POST    http://127.0.0.1:8085/api/centreon/autodiscovery/services    json=${data}    expected_status=200

    Log    ${launchDiscoveryResponse.json()}[token]
    Sleep    1
    ${getDiscoveryResultResponse}=    GET    http://127.0.0.1:8085/api/log/${launchDiscoveryResponse.json()}[token]    expected_status=200
    ${IsGorgoneDone}=    Is Gorgone Finished    ${getDiscoveryResultResponse.json()}
    WHILE    ${IsGorgoneDone} == 0
        ${getDiscoveryResultResponse}=    GET    http://127.0.0.1:8085/api/log/${launchDiscoveryResponse.json()}[token]    expected_status=200
        ${IsGorgoneDone}=    Is Gorgone Finished    ${getDiscoveryResultResponse.json()}
        Sleep    2
    END
    DatabaseLibrary.Row Count is equal To X    ${sqlRequest}    50

*** Keywords ***
prepare db
    [Arguments]   ${id}=1
    ${insert_host_req}=    Catenate    SEPARATOR=
        ...    INSERT INTO host (host_name, host_alias, host_address, host_active_checks_enabled, 
        ...    host_passive_checks_enabled, host_obsess_over_host, host_check_freshness, host_event_handler_enabled, 
        ...    host_flap_detection_enabled, host_retain_status_information, host_retain_nonstatus_information,
        ...    host_notifications_enabled, contact_additive_inheritance, cg_additive_inheritance, host_locked, host_register, host_activate) 
        ...    VALUES('gorgone_auto_discovery_test_${id}', 'gorgone_auto_discovery_test_${id}', '127.0.0.1', '2', '2', '2', '2', '2', '2', '2', '2', '2',
        ...    '0', '0', '0', '1', '1');

    ${output} =    Execute SQL String    ${insert_host_req}

    ${output2} =    Execute SQL String    INSERT INTO host_template_relation (`host_host_id`, `host_tpl_id`, `order`) VALUES((select LAST_INSERT_ID()), 19, 1);
    ${output3} =    Execute SQL String    INSERT INTO `ns_host_relation` (`host_host_id`, `nagios_server_id`) VALUES ((select LAST_INSERT_ID()), '1');