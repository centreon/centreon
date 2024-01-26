
*** Settings ***
Documentation     centreon gorgone auto discovery tests
Library            OperatingSystem
Library            RequestsLibrary
Library            gorgone-api.py
Library             DatabaseLibrary
Suite Setup    Connect To Database    pymysql    ${DBName}    ${DBUser}    ${DBPass}    ${DBHost}    ${DBPort}
Test Setup    Prepare Db    ${nb_hosts}

Test Teardown    Remove Services And Host
Suite Teardown    Disconnect From Database
Test Template    Gorgone Test

*** Variables ***
${nb_hosts}    50
${DBName}           centreon
${DBHost}           localhost
${DBUser}           robotTest
${DBPass}           password
${DBPort}           3306
${sqlRequest}        select * from service JOIN host_service_relation ON service_service_id = service_id JOIN host ON host_service_relation.host_host_id = host.host_id WHERE service_description like 'Disk-/' 	AND host.host_alias LIKE 'gorgone_auto_discovery_test_%';
# To work this test need :
# access to the centreon database with write permission
# Snmp must answer all disk info on 127.0.0.1
# Centreon must have "generic snmp" plugin pack installed and "OS-Linux-SNMP-Disk-Name" discovery rule enabled.
*** Test Cases ***    nb_host
Test Gorgone 1    50
Test Gorgone 2    50
Test Gorgone 3    50
Test Gorgone 4    50
Test Gorgone 5    50
Test Gorgone 6    50
Test Gorgone 7    50
Test Gorgone 8    50
Test Gorgone 9    50
Test Gorgone 10    50
Test Gorgone 11    50
Test Gorgone 12    50
Test Gorgone 13    50
Test Gorgone 14    50
Test Gorgone 15    50
Test Gorgone 16    50
Test Gorgone 17    50
Test Gorgone 18    50
Test Gorgone 19    50
Test Gorgone 20    50
Test Gorgone 21    50
Test Gorgone 22    50
Test Gorgone 23    50
Test Gorgone 24    50
Test Gorgone 25    50
Test Gorgone 26    50
Test Gorgone 27    50
Test Gorgone 28    50
Test Gorgone 29    50
Test Gorgone 30    50
Test Gorgone 31    50
Test Gorgone 32    50
Test Gorgone 33    50
Test Gorgone 34    50
Test Gorgone 35    50
Test Gorgone 36    50
Test Gorgone 37    50
Test Gorgone 38    50
Test Gorgone 39    50
Test Gorgone 40    50
Test Gorgone 41    50
Test Gorgone 42    50
Test Gorgone 43    50
Test Gorgone 44    50
Test Gorgone 45    50
Test Gorgone 46    50
Test Gorgone 47    50
Test Gorgone 48    50
Test Gorgone 49    50
Test Gorgone 50    50


*** Keywords ***
gorgone test
    [Arguments]   ${nb_hosts}=50
    Run Test
    #Remove Services And Host

prepare db
    [Arguments]   ${nb_hosts}=50


    FOR    ${id}    IN RANGE    0    ${nb_hosts}

        ${insert_host_req}=    Catenate    SEPARATOR=
            ...    INSERT IGNORE INTO host (host_name, host_alias, host_address, host_active_checks_enabled,
            ...    host_passive_checks_enabled, host_obsess_over_host, host_check_freshness, host_event_handler_enabled, 
            ...    host_flap_detection_enabled, host_retain_status_information, host_retain_nonstatus_information,
            ...    host_notifications_enabled, contact_additive_inheritance, cg_additive_inheritance, host_locked, host_register, host_activate) 
            ...    VALUES('gorgone_auto_discovery_test_${id}', 'gorgone_auto_discovery_test_${id}', '127.0.0.1', '2', '2', '2', '2', '2', '2', '2', '2', '2',
            ...    '0', '0', '0', '1', '1');
    
        ${output} =    Execute SQL String    ${insert_host_req}
    
        ${output2} =    Execute SQL String    INSERT INTO host_template_relation (`host_host_id`, `host_tpl_id`, `order`) VALUES((select LAST_INSERT_ID()), (select host_id from host where host_name = 'OS-Linux-SNMP-custom'), 1);
        ${output3} =    Execute SQL String    INSERT INTO `ns_host_relation` (`host_host_id`, `nagios_server_id`) VALUES ((select LAST_INSERT_ID()), '1');
    END

run test
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
    DatabaseLibrary.Row Count is equal To X    ${sqlRequest}    ${nb_hosts}

remove services and host
    ${delete_service}=     Catenate
    ...    DELETE service FROM service
    ...    JOIN host_service_relation
    ...        ON service_service_id = service_id
    ...    JOIN host
    ...        ON host.host_id = host_service_relation.host_host_id 
    ...    WHERE service_description like 'Disk-/'
    ...        AND host.host_alias LIKE 'gorgone_auto_discovery_test_%'

    ${output} =    Execute SQL String    ${delete_service}
    ${output} =    Execute SQL String    DELETE host FROM host where host.host_alias LIKE 'gorgone_auto_discovery_test_%'
    ${output} =    Execute SQL String    DELETE host_template_relation FROM host_template_relation WHERE host_host_id NOT IN (SELECT host_id FROM host)
    ${output} =    Execute SQL String    DELETE FROM ns_host_relation WHERE host_host_id NOT IN (SELECT host_id FROM host)

