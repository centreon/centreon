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
        "note": "note",
        "note_url": "note_url",
        "action_url": "action url",
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
        "host_id": 15
      }
      """
    Then the response code should be 409