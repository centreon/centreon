Feature:
  In order to check the hosts
  As a logged in user
  I want to find host temlates using api

  Background:
    Given a running cloud platform instance of Centreon Web API
    And the endpoints are described in Centreon Web API documentation

  Scenario: Host creation
    Given I am logged in
    And the following CLAPI import data:
    """
    HC;ADD;severity1;host-severity-alias
    HC;setseverity;severity1;42;logos/logo-centreon-colors.png
    HC;ADD;host-cat1;host-cat1-alias
    """

    When I send a POST request to '/api/latest/configuration/hosts' with body:
      """
      {
        "monitoring_server_id": 1,
        "name": "  host name A  ",
        "address": "127.0.0.1",
        "alias": "  host-alias  ",
        "geo_coords": "42.12,15.2",
        "snmp_version": "2c",
        "snmp_community": "   snmpCommunity-value",
        "timezone_id": 1,
        "severity_id": 1,
        "check_timeperiod_id": 1,
        "note_url": "noteUrl-value",
        "note": "note-value",
        "action_url": "actionUrl-value",
        "categories": [2],
        "templates": [],
        "macros": [
          {
            "name": "nameA",
            "value": "valueA",
            "is_password": false,
            "description": "some text"
          },
          {
            "name": "nameB",
            "value": "valueB",
            "is_password": true,
            "description": null
          }
        ]
      }
      """
    Then the response code should be "201"
    And the JSON should be equal to:
      """
      {
        "id": 15,
        "monitoring_server_id": 1,
        "name": "host_name_A",
        "address": "127.0.0.1",
        "snmp_version": "2c",
        "geo_coords": "42.12,15.2",
        "alias": "host-alias",
        "snmp_community": "snmpCommunity-value",
        "note_url": "noteUrl-value",
        "note": "note-value",
        "action_url": "actionUrl-value",
        "timezone_id": 1,
        "severity_id": 1,
        "check_timeperiod_id": 1,
        "categories": [
          {
            "id": 2,
            "name": "host-cat1"
          }
        ],
        "templates": [],
        "macros": [
          {
            "name": "NAMEA",
            "value": "valueA",
            "is_password": false,
            "description": "some text"
          },
          {
            "name": "NAMEB",
            "value": null,
            "is_password": true,
            "description": null
          }
        ]
      }
      """

    When I send a POST request to '/api/latest/configuration/hosts' with body:
      """
      {
        "monitoring_server_id": 1,
        "name": "host name A",
        "address": "127.0.0.1"
      }
      """
    Then the response code should be "409"

    When I send a POST request to '/api/latest/configuration/hosts/templates' with body:
      """
      {
        "name": "  host template name  ",
        "alias": "  host-template-alias  ",
        "macros": [
          {
            "name": "nameA",
            "value": "valueA",
            "is_password": false,
            "description": "some text"
          },
          {
            "name": "nameB",
            "value": "valueB",
            "is_password": true,
            "description": null
          }
        ]
      }
      """
    Then the response code should be "201"
    And I store response values in:
      | name             | path |
      | hostTemplateId   | id   |
      | hostTemplateName | name |

    Given the following CLAPI import data:
      """
      HC;ADD;host-cat2;host-cat2-alias
      CONTACT;ADD;ala;ala;ala@localhost.com;Centreon@2022;0;1;en_US;local
      CONTACT;setparam;ala;reach_api;1
      ACLMENU;add;ACL Menu test;my alias
      ACLMENU;grantrw;ACL Menu test;1;Configuration;Hosts;Hosts
      ACLRESOURCE;add;ACL Resource test;my alias
      ACLRESOURCE;addfilter_hostcategory;ACL Resource test;host-cat2
      ACLGROUP;add;ACL Group test;my alias
      ACLGROUP;addmenu;ACL Group test;ACL Menu test
      ACLGROUP;addresource;ACL Group test;ACL Resource test
      ACLGROUP;addcontact;ACL Group test;ala
      """
    And I am logged in with "ala"/"Centreon@2022"

    # use invalid category ID
    When I send a POST request to '/api/latest/configuration/hosts' with body:
      """
      {
        "monitoring_server_id": 1,
        "name": "  host name B  ",
        "address": "127.0.0.1",
        "alias": "  host-alias  ",
        "geo_coords": "42.12,15.2",
        "snmp_version": "2c",
        "snmp_community": "   snmpCommunity-value",
        "timezone_id": 1,
        "severity_id": 1,
        "check_timeperiod_id": 1,
        "note_url": "noteUrl-value",
        "note": "note-value",
        "action_url": "actionUrl-value",
        "categories": [2],
        "templates": []
      }
      """
    Then the response code should be "409"

    # use invalid template ID
    When I send a POST request to '/api/latest/configuration/hosts' with body:
      """
      {
        "monitoring_server_id": 1,
        "name": "  host name B  ",
        "address": "127.0.0.1",
        "alias": "  host-alias  ",
        "geo_coords": "42.12,15.2",
        "snmp_version": "2c",
        "snmp_community": "   snmpCommunity-value",
        "timezone_id": 1,
        "severity_id": 1,
        "check_timeperiod_id": 1,
        "note_url": "noteUrl-value",
        "note": "note-value",
        "action_url": "actionUrl-value",
        "categories": [],
        "templates": [999]
      }
      """
    Then the response code should be "409"

    Given the following CLAPI import data:
      """
      ACLRESOURCE;addfilter_hostcategory;ACL Resource test;host-cat1
      """

    # macro should not appear in response as they are inherited from parent template
    When I send a POST request to '/api/latest/configuration/hosts' with body:
      """
      {
        "monitoring_server_id": 1,
        "name": "  host name B  ",
        "address": "127.0.0.1",
        "alias": "  host-alias  ",
        "geo_coords": "42.12,15.2",
        "snmp_version": "2c",
        "snmp_community": "   snmpCommunity-value",
        "timezone_id": 1,
        "severity_id": 1,
        "check_timeperiod_id": 1,
        "note_url": "noteUrl-value",
        "note": "note-value",
        "action_url": "actionUrl-value",
        "categories": [2],
        "templates": [<hostTemplateId>],
        "macros": [
          {
            "name": "nameA",
            "value": "valueA",
            "is_password": false,
            "description": "some text"
          },
          {
            "name": "nameB",
            "value": "valueB",
            "is_password": true,
            "description": null
          }
        ]
      }
      """
    Then the response code should be "201"
    And the JSON should be equal to:
      """
      {
        "id": 19,
        "monitoring_server_id": 1,
        "name": "host_name_B",
        "address": "127.0.0.1",
        "snmp_version": "2c",
        "geo_coords": "42.12,15.2",
        "alias": "host-alias",
        "snmp_community": "snmpCommunity-value",
        "note_url": "noteUrl-value",
        "note": "note-value",
        "action_url": "actionUrl-value",
        "timezone_id": 1,
        "severity_id": 1,
        "check_timeperiod_id": 1,
        "categories": [
          {
            "id": 2,
            "name": "host-cat1"
          }
        ],
        "templates": [
          {
            "id": <hostTemplateId>,
            "name": <hostTemplateName>
          }
        ],
        "macros": []
      }
      """
