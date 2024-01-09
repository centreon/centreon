*** Settings ***
Documentation     centreon gorgone start/stop tests

Library             Process
Library             DateTime
Library             OperatingSystem
Library             Examples


*** TesT Cases ***
GSS${id}
    [Documentation]    Start-Stop Gorgone
    [Tags]    gorgone    start-stop
    Set Log Level    TRACE
    FOR  ${i}  IN RANGE    10
        ${result}    Run Process    service gorgoned start    shell=True

        ${result}    Run Process    service gorgoned status    shell=True
        Log    ${result.stdout}
        Should Contain   ${result.stdout}    active (running)
        Sleep    ${time}s

        ${result}    Run Process    service gorgoned stop    shell=True

        ${result}    Run Process    service gorgoned status    shell=True
        Log    ${result.stdout}
        Should Contain   ${result.stdout}    inactive (dead)
        Sleep    ${time}s
    END

    Examples:    id    time    --
    ...          1     1
    ...          2     10
