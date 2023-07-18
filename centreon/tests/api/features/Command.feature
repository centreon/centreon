Feature:
  In order to check the commands
  As a logged in user
  I want to find commands using api

  Background:
    Given a running instance of Centreon Web API
    And the endpoints are described in Centreon Web API documentation

  Scenario: Command listing
    Given I am logged in
    And the following CLAPI import data:
    """
    CONTACT;ADD;test;test;test@localhost.com;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;test;reach_api;1
    CONTACT;ADD;test2;test2;test@localhost.com;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;test2;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;grantrw;ACL Menu test;1;Configuration;Commands;Checks
    ACLGROUP;add;ACL Group test;my alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addcontact;ACL Group test;test
    """

    When I send a GET request to '/api/latest/configuration/commands?search={"name":"check_host_alive"}'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 1,
                "name": "check_host_alive",
                "type": 2,
                "command_line": "$USER1$/check_icmp -H $HOSTADDRESS$ -w 3000.0,80% -c 5000.0,100% -p 1",
                "is_shell": false,
                "is_locked": false,
                "is_activated": true
            }
        ],
        "meta": {
            "page": 1,
            "limit": 10,
            "search": {
                "$and": {
                    "name": "check_host_alive"
                }
            },
            "sort_by": {},
            "total": 1
        }
    }
    """

    Given I am logged in with "test"/"Centreon@2022"
    When I send a GET request to '/api/latest/configuration/commands'
    Then the response code should be "200"

    Given I am logged in with "test2"/"Centreon@2022"
    When I send a GET request to '/api/latest/configuration/commands'
    Then the response code should be "403"

