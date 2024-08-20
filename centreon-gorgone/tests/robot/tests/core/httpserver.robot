*** Settings ***
Documentation       check gorgone api response
Suite Setup         Setup Gorgone
Suite Teardown      Stop Gorgone And Remove Gorgone Config    httpserver_api_statuscode
Resource            ${CURDIR}${/}..${/}..${/}resources${/}import.resource
Test Timeout        220s

*** Variables ***


*** Test Cases ***
check http api get status code ${tc}
    ${expected_code}=    Convert To Integer    ${http_status_code}
    ${api_response}=    GET  http://127.0.0.1:8085${endpoint}    expected_status=anything

    Log To Console    \nendpoint code is : ${api_response.status_code} output is : ${api_response.text}

    Should Be Equal    ${api_response.status_code}    ${expected_code}
    ${expected_json}=    evaluate    json.loads('''${expected_response}''')    json
    Dictionaries Should Be Equal    ${api_response.json()}    ${expected_json}

    Examples:        tc    http_status_code    endpoint    expected_response    --
            ...      forbidden     403    /bad/endpoint     {"error":"http_error_403","message":"forbidden"}
            ...      constatus Ok     200    /api/internal/constatus    {"data":{},"action":"constatus","message":"ok"}
            ...      method not found     404    /api/internal/wrongendpoint    {"error":"method_unknown","message":"Method not implemented"}
            ...      get log    200     /api/nodes/1/log/wrongtoken    {"error":"no_log","message":"No log found for token","data":[],"token":"wrongtoken"}
        
check http api post api ${tc}
    ${expected_code}=    Convert To Integer    ${http_status_code}
    ${api_response}=    POST  http://127.0.0.1:8085${endpoint}    expected_status=anything    data=${body}

    Log To Console    \nendpoint code is : ${api_response.status_code} output is : ${api_response.text}

    Should Be Equal    ${api_response.status_code}    ${expected_code}
    IF    len("""${expected_response}""") > 0
        ${expected}=    evaluate    json.loads('''${expected_response}''')    json
        Dictionaries Should Be Equal    ${api_response.json()}    ${expected}
    END

    Examples:        tc    http_status_code    endpoint    body    expected_response    --
            ...      body is not json     400    /api/centreon/nodes/sync     {    {"error":"decode_error","message":"POST content must be JSON-formated"}
            ...      body is valid json     200    /api/centreon/nodes/sync     {}    ${EMPTY}        # api send back a random token.


*** Keywords ***

Setup Gorgone
    Setup Gorgone Config    ${push_central_config}    ${gorgone_core_config}    gorgone_name=httpserver_api_statuscode
    Start Gorgone    debug    httpserver_api_statuscode

    Log To Console    \nGorgone Started. We have to wait for it to be ready to respond.
    Sleep    10
    Log To Console    Gorgone should be ready. \n