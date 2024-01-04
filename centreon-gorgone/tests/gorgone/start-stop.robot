*** Settings ***
Documentation     centreon gorgone start/stop tests

Library             Process
Library             DateTime
Library             OperatingSystem


*** TesT Cases ***
GSS1
    [Documentation]    Start-Stop Gorgone
    [Tags]    gorgone    start-stop
    Set Log Level    TRACE
    ${result}    Run Process    service gorgoned start    shell=True

    ${result}    Run Process    service gorgoned status    shell=True
    Log    ${result.stdout}
    Should Contain   ${result.stdout}    active (running)

    ${result}    Run Process    service gorgoned stop    shell=True

    ${result}    Run Process    service gorgoned status    shell=True
    Log    ${result.stdout}
    Should Contain   ${result.stdout}    inactive (dead)