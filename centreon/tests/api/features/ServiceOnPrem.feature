Feature:
  In order to check the service
  As a logged in user
  I want to find service using api

  Background:
    Given a running instance of Centreon Web API
    And the endpoints are described in Centreon Web API documentation

  Scenario: Service delete
    Given the following CLAPI import data:
      """
      HOST;add;Host-Test;Host-Test-alias;127.0.0.1;;central;
      SERVICE;add;Host-Test;Service-Test;Ping-LAN
      """
    And I am logged in

    When I send a DELETE request to '/api/latest/configuration/services/99'
    Then the response code should be "404"

    Then I send a DELETE request to '/api/latest/configuration/services/27'
    Then the response code should be "204"

    Given the following CLAPI import data:
      """
      CONTACT;ADD;test;test;test@localhost.com;Centreon@2022;0;1;en_US;local
      CONTACT;setparam;test;reach_api;1
      HOST;add;Host-Test;Host-Test-alias;127.0.0.1;;central;
      SERVICE;add;Host-Test;Service-Test;Ping-LAN
      HOST;add;Host-Test2;Host-Test-alias2;127.0.0.1;;central;
      SERVICE;add;Host-Test2;Service-Test;Ping-LAN
      ACLMENU;add;ACL Menu test;my alias
      ACLMENU;grantrw;ACL Menu test;0;Configuration;Hosts;
      ACLMENU;grantrw;ACL Menu test;0;Configuration;Hosts;Hosts;
      ACLMENU;grantrw;ACL Menu test;0;Configuration;Services;
      ACLMENU;grantrw;ACL Menu test;0;Configuration;Services;Services by host;
      ACLRESOURCE;add;ACL Resource test;my alias
      ACLRESOURCE;grant_host;ACL Resource test;Host-Test
      ACLGROUP;add;ACL Group test;ACL Group test alias
      ACLGROUP;addmenu;ACL Group test;ACL Menu test
      ACLGROUP;addresource;ACL Group test;ACL Resource test
      ACLGROUP;setcontact;ACL Group test;test;
      ACL;reload
      """
    And I am logged in with "test"/"Centreon@2022"

    When I send a DELETE request to '/api/latest/configuration/services/28'
    Then the response code should be "204"

    Then I send a DELETE request to '/api/latest/configuration/services/29'
    Then the response code should be "204"

    When I send a DELETE request to '/api/latest/configuration/services/99'
    Then the response code should be "404"


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
        "comment": "comment",
        "check_command_id": 1,
        "check_command_args": ["arg1", "arg2"],
        "check_timeperiod_id": 1,
        "max_check_attempts": 5,
        "normal_check_interval": 3,
        "retry_check_interval": 2,
        "active_check_enabled": 2,
        "passive_check_enabled": 2,
        "volatility_enabled": 2,
        "notification_enabled": 2,
        "is_contact_additive_inheritance": false,
        "is_contact_group_additive_inheritance": false,
        "notification_interval": 6,
        "notification_timeperiod_id": 2,
        "notification_type": 36,
        "first_notification_delay": 19,
        "recovery_notification_delay": 9,
        "acknowledgement_timeout": 8,
        "freshness_checked": 2,
        "freshness_threshold": 11,
        "flap_detection_enabled": 2,
        "low_flap_threshold": 49,
        "high_flap_threshold": 51,
        "event_handler_enabled": 2,
        "event_handler_command_id": 2,
        "event_handler_command_args": ["earg1", "earg2"],
        "graph_template_id": 1,
        "note": "note",
        "note_url": "note_url",
        "action_url": "action url",
        "icon_id": 1,
        "icon_alternative": "icon_alternative",
        "severity_id": null,
        "geo_coords": "12.34,23.5",
        "is_activated": true,
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
        "comment": "comment",
        "service_template_id": <templateA>,
        "check_command_id": 1,
        "check_command_args": [
          "arg1",
          "arg2"
        ],
        "check_timeperiod_id": 1,
        "max_check_attempts": 5,
        "normal_check_interval": 3,
        "retry_check_interval": 2,
        "active_check_enabled": 2,
        "passive_check_enabled": 2,
        "volatility_enabled": 2,
        "notification_enabled": 2,
        "is_contact_additive_inheritance": false,
        "is_contact_group_additive_inheritance": false,
        "notification_interval": 6,
        "notification_timeperiod_id": 2,
        "notification_type": 36,
        "first_notification_delay": 19,
        "recovery_notification_delay": 9,
        "acknowledgement_timeout": 8,
        "freshness_checked": 2,
        "freshness_threshold": 11,
        "flap_detection_enabled": 2,
        "low_flap_threshold": 49,
        "high_flap_threshold": 51,
        "event_handler_enabled": 2,
        "event_handler_command_id": 2,
        "event_handler_command_args": [
          "earg1",
          "earg2"
        ],
        "graph_template_id": 1,
        "note": "note",
        "note_url": "note_url",
        "action_url": "action url",
        "icon_id": 1,
        "icon_alternative": "icon_alternative",
        "geo_coords": "12.34,23.5",
        "severity_id": null,
        "is_activated": true,
        "macros": [
          {
          "name": "MACROB",
          "value": "B",
          "is_password": false,
          "description": ""
          }
        ],
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
    And the json node "result[8].hosts" should have 1 elements
    And the json node "result[8].categories" should have 1 elements
    And the json node "result[8].groups" should have 1 elements
    And the JSON nodes should be equal to:
      | result[8].id                    | 27              |
      | result[8].name                  | "service-name"  |
      | result[8].hosts[0].name         | "host-name"     |
      | result[8].service_template.name | "Ping-LAN"      |
      | result[8].check_timeperiod.name | "24x7"          |
      | result[8].severity.name         | "severity-name" |
      | result[8].categories[0].name    | "Ping"          |
      | result[8].groups[0].name        | "group-name"    |
      | result[8].groups[0].host_name   | "host-name"     |
      | result[8].is_activated          | true            |

    Given the following CLAPI import data:
      """
      CONTACT;ADD;test;test;test@localhost.com;Centreon@2022;0;1;en_US;local
      CONTACT;setparam;test;reach_api;1
      HOST;add;Host-Test;Host-Test-alias;127.0.0.1;;central;
      SERVICE;add;Host-Test;Service-Test;Ping-LAN
      HOST;add;Host-Test2;Host-Test-alias2;127.0.0.1;;central;
      SERVICE;add;Host-Test2;Service-Test;Ping-LAN
      ACLMENU;add;ACL Menu test;my alias
      ACLMENU;grantrw;ACL Menu test;0;Configuration;Hosts;
      ACLMENU;grantrw;ACL Menu test;0;Configuration;Hosts;Hosts;
      ACLMENU;grantrw;ACL Menu test;0;Configuration;Services;
      ACLMENU;grantrw;ACL Menu test;0;Configuration;Services;Services by host;
      ACLRESOURCE;add;ACL Resource test;my alias
      ACLRESOURCE;grant_host;ACL Resource test;Host-Test
      ACLGROUP;add;ACL Group test;ACL Group test alias
      ACLGROUP;addmenu;ACL Group test;ACL Menu test
      ACLGROUP;addresource;ACL Group test;ACL Resource test
      ACLGROUP;setcontact;ACL Group test;test;
      ACL;reload
      """
    And I am logged in with "test"/"Centreon@2022"

    When I send a GET request to '/api/latest/configuration/services'
    And the json node "result" should have 1 elements
    And the json node "result[0].id" should be equal to the number 28


