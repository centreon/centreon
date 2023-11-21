Feature:
  In order to check the service
  As a logged in user
  I want to find service using api

  Background:
    Given a running cloud platform instance of Centreon Web API
    And the endpoints are described in Centreon Web API documentation

  Scenario: Service delete
    Given the following CLAPI import data:
      """
      CONTACT;ADD;test;test;test@localhost.com;Centreon@2022;0;1;en_US;local
      CONTACT;setparam;test;reach_api;1
      HOST;add;Host-Test;Host-Test-alias;127.0.0.1;;central;
      SERVICE;add;Host-Test;Service-Test;Ping-LAN
      """
    And I am logged in with "test"/"Centreon@2022"

    When I send a DELETE request to '/api/latest/configuration/services/27'
    Then the response code should be "403"

    Given I am logged in
    When I send a DELETE request to '/api/latest/configuration/services/99'
    Then the response code should be "404"

    Then I send a DELETE request to '/api/latest/configuration/services/27'
    Then the response code should be "204"

  Scenario: Service creation
    Given I am logged in
    And the following CLAPI import data:
      """
      HOST;add;Host-Test;Host-Test-alias;127.0.0.1;;central;
      SG;ADD;ServiceGroupA;ServiceGroupA
      """

    When I send a POST request to '/api/latest/configuration/services/templates' with body:
      """
      {
        "name": "templateA",
        "alias": "templateA",
        "macros": [
          {
          "name": "MACROA",
          "value": "A",
          "is_password": false,
          "description": null
          }
        ]
      }
      """
    Then the response code should be 201
    And I store response values in:
      | name      | path |
      | templateA | id   |

    When I send a POST request to '/api/latest/configuration/services' with body:
      """
      {
        "name": "service test",
        "host_id": 15,
        "service_template_id": <templateA>,
        "check_timeperiod_id": 1,
        "note": "note",
        "note_url": "note_url",
        "action_url": "action url",
        "severity_id": null,
        "geo_coords": "12.34,23.5",
        "service_categories": [1, 2],
        "macros": [
          {
            "name": "MACROA",
            "value": "A",
            "is_password": false,
            "description": null
          },
          {
            "name": "MACROB",
            "value": "B",
            "is_password": false,
            "description": null
          }
        ],
        "service_groups": [1]
      }
      """
    Then the response code should be 201
    And the JSON should be equal to:
      """
      {
        "id": 28,
        "name": "service test",
        "host_id": 15,
        "service_template_id": <templateA>,
        "check_timeperiod_id": 1,
        "max_check_attempts": null,
        "normal_check_interval": null,
        "retry_check_interval": null,
        "note": "note",
        "note_url": "note_url",
        "action_url": "action url",
        "geo_coords": "12.34,23.5",
        "icon_id": null,
        "severity_id": null,
        "categories": [
          {
            "id": 1,
            "name": "Ping"
          },
          {
            "id": 2,
            "name": "Traffic"
          }
        ],
        "groups": [
          {
            "id": 1,
            "name": "ServiceGroupA"
          }
        ],
        "macros": [
          {
            "name": "MACROB",
            "value": "B",
            "is_password": false,
            "description": ""
          }
        ]
      }
      """

    When I send a POST request to '/api/latest/configuration/services' with body:
      """
      {
        "name": "service test",
        "host_id": 15,
        "service_template_id": <templateA>
      }
      """
    Then the response code should be 409

  Scenario: Service listing
    Given I am logged in
    And the following CLAPI import data:
      """
      HOST;add;host-name;host-alias;127.0.0.1;;central;
      SERVICE;add;host-name;service-name;Ping-LAN
      SERVICE;setparam;host-name;service-name;check_period;24x7
      SC;add;severity-name;severity-alias
      SC;setseverity;severity-name;3;logos/logo-centreon-colors.png
      SC;addservice;Ping;host-name,service-name
      SC;addservice;severity-name;host-name,service-name
      SG;add;group-name;group-alias
      SG;addservice;group-name;host-name,service-name
      """

    When I send a GET request to '/api/latest/configuration/services'
    And the json node "result" should have 9 elements
    And the json node "result[8].host" should have 1 elements
    And the json node "result[8].categories" should have 1 elements
    And the json node "result[8].groups" should have 1 elements
    And the JSON nodes should be equal to:
      | result[8].id                    | 27               |
      | result[8].name                  | "service-name"   |
      | result[8].host[0].name          | "host-name"      |
      | result[8].service_template.name | "Ping-LAN"       |
      | result[8].check_timeperiod.name | "24x7"           |
      | result[8].severity.name         | "severity-name"  |
      | result[8].categories[0].name    | "Ping"           |
      | result[8].groups[0].name        | "group-name"     |
      | result[8].groups[0].host_name   | "host-name"      |
      | result[8].is_activated          | true             |
