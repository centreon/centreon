*** Settings ***
Documentation       test gorgone statistics module

Resource            ${CURDIR}${/}..${/}..${/}resources${/}import.resource
Test Timeout        220s
Suite Setup         Suite Setup Statistics module

*** Variables ***
${db_storage}    Will contain the centreon-storage database connection

*** Test Cases ***
check statistic module add all centengine data in db ${communication_mode}
    @{process_list}    Create List    ${communication_mode}_gorgone_central    ${communication_mode}_gorgone_poller_2
    [Teardown]    Stop Gorgone And Remove Gorgone Config    @{process_list}    sql_file=${ROOT_CONFIG}db_delete_poller.sql

    @{central_config}    Create List    ${ROOT_CONFIG}statistics.yaml    ${ROOT_CONFIG}actions.yaml
    @{poller_config}    Create List    ${ROOT_CONFIG}actions.yaml
    Setup Two Gorgone Instances    central_config=${central_config}    communication_mode=${communication_mode}    central_name=${communication_mode}_gorgone_central    poller_name=${communication_mode}_gorgone_poller_2

    # we first test the module when there is no data in the table, then we test it again when
    # there is data in the table to be sure the data are correctly updated.
    Query    DELETE FROM nagios_stats    alias=${db_storage}

    Ctn Gorgone Force Engine Statistics Retrieve
    # statistics module send the GORGONE_ACTION_FINISH_OK once messages for the action module are sent.
    # It don't wait for the action module to send back data or for the processing of the response to be finished.
    # So I added a log each time a poller stat have finished to be processed. In this test I know
    # I have 2 log because there is the central and one poller.
    ${log}    Create List    poller . engine data was integrated in rrd and sql database.    poller . engine data was integrated in rrd and sql database.
    
    ${result}    Ctn Find In Log With Timeout    /var/log/centreon-gorgone/${communication_mode}_gorgone_central/gorgoned.log    ${log}    regex=1
    ${nb_logs}    Get Length    ${result}
    Should Be True    ${nb_logs} == 2    there was not 2 log found : ${result}
    Ctn Gorgone Check Poller Engine Stats Are Present    poller_id=1
    Ctn Gorgone Check Poller Engine Stats Are Present    poller_id=2

    # As the value we set in db are fake and hardcoded, we need to change the data before
    # running again the module to be sure data are correctly updated, instead of letting the last value persist.
    Query    UPDATE nagios_stats SET stat_value=999    alias=${db_storage}
    ${date}    Get Current Date
    Ctn Gorgone Force Engine Statistics Retrieve
    
    ${result}    Ctn Find In Log With Timeout    log=/var/log/centreon-gorgone/${communication_mode}_gorgone_central/gorgoned.log    content=${log}    date=${date}    regex=1
    Ctn Gorgone Check Poller Engine Stats Are Present    poller_id=1
    Ctn Gorgone Check Poller Engine Stats Are Present    poller_id=2

    Examples:    communication_mode   --
        ...    push_zmq
  #      ...    pullwss

*** Keywords ***
Suite Setup Statistics module
    Set Centenginestat Binary
    Connect To Database    pymysql    ${DBNAME_STORAGE}    ${DBUSER}    ${DBPASSWORD}    ${DBHOST}    ${DBPORT}
    ...    alias=${db_storage}

Set Centenginestat Binary
    [Documentation]    this keyword add a centenginestats file from the local directory to the /usr/sbin directory and make it executable. This allow to test the gorgone statistics module without installing centreon-engine and starting the service

    Copy File    ${CURDIR}${/}centenginestats    /usr/sbin/centenginestats
    Run    chmod 755 /usr/sbin/centenginestats

Ctn Gorgone Check Poller Engine Stats Are Present
    [Arguments]    ${poller_id}=
    
    &{Service Check Latency}=           Create Dictionary 	  Min=0.102    Max=0.955    Average=0.550
    &{Host Check Latency}=              Create Dictionary 	  Min=0.020    Max=0.868    Average=0.475
    &{Service Check Execution Time}=    Create Dictionary 	  Min=0.001    Max=0.332    Average=0.132
    &{Host Check Execution Time}=       Create Dictionary 	  Min=0.030    Max=0.152    Average=0.083
    
    &{data_check}    Create Dictionary    Service Check Latency=&{Service Check Latency}    Host Check Execution Time=&{Host Check Execution Time}    Host Check Latency=&{Host Check Latency}    Service Check Execution Time=&{Service Check Execution Time}

    FOR    ${stat_label}    ${stat_data}    IN    &{data_check}
        
        FOR    ${stat_key}    ${stat_value}    IN    &{stat_data}
            Check If Exists In Database    SELECT instance_id FROM nagios_stats WHERE stat_key = '${stat_key}' AND stat_value = '${stat_value}' AND stat_label = '${stat_label}' AND instance_id='${poller_id}';    alias=${db_storage}

        END
    END

Ctn Gorgone Force Engine Statistics Retrieve
    ${response}=    GET  http://127.0.0.1:8085/api/centreon/statistics/engine
    Log To Console    ${response.json()}
    Dictionary Should Not Contain Key  ${response.json()}    error    api/centreon/statistics/engine api call resulted in an error : ${response.json()}

    Log To Console    we successfully got the response : ${response.json()}
