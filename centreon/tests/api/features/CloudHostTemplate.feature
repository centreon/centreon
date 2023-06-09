Feature:
  In order to check the host templates
  As a logged in user
  I want to find host temlates using api

  Background:
    Given a running cloud platform instance of Centreon Web API
    And the endpoints are described in Centreon Web API documentation

  Scenario: Host templates listing
    Given I am logged in
    And the following CLAPI import data:
    """
    HTPL;ADD;htpl-name-1;htpl-alias-1;;;;
    """

    When I send a GET request to '/api/latest/configuration/hosts/templates?search={"name":{"$lk":"htpl-%"}}'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
      "result": [
        {
          "id": 15,
          "name": "htpl-name-1",
          "alias": "htpl-alias-1",
          "snmp_version": null,
          "snmp_community": null,
          "timezone_id": null,
          "severity_id": null,
          "check_timeperiod_id": null,
          "note_url": null,
          "note": null,
          "action_url": null,
          "is_activated": true,
          "is_locked": false
        }
      ],
      "meta": {
        "page": 1,
        "limit": 10,
        "search": {
          "$and": {
            "name": {
            "$lk": "htpl-%"
            }
          }
        },
        "sort_by": {},
        "total": 1
      }
    }
    """

  Scenario: Host template deletion
    Given I am logged in
    And the following CLAPI import data:
      """
      HTPL;ADD;htpl-name-1;htpl-alias-1;;;;
      """

    When I send a GET request to '/api/latest/configuration/hosts/templates?search={"name":{"$lk":"htpl-%"}}'
    Then the response code should be "200"
    And I store response values in:
      | name           | path         |
      | hostTemplateId | result[0].id |

    When I send a DELETE request to '/api/latest/configuration/hosts/templates/<hostTemplateId>'
    Then the response code should be "204"

    When I send a GET request to '/api/latest/configuration/hosts/templates?search={"name":{"$lk":"htpl-%"}}'
    Then the response code should be "200"
    And the json node "result" should have 0 elements

  Scenario: Host template creation
    Given I am logged in
    And the following CLAPI import data:
    """
    HC;ADD;severity1;host-severity-alias
    HC;setseverity;severity1;42;logos/logo-centreon-colors.png
    HC;ADD;host-cat1;host-cat1-alias
    """

    When I send a POST request to '/api/latest/configuration/hosts/templates' with body:
      """
      {
        "name": "  host template name A  ",
        "alias": "  host-template-alias  ",
        "snmp_version": "2c",
        "snmp_community": "   snmpCommunity-value",
        "timezone_id": 1,
        "severity_id": 1,
        "check_timeperiod_id": 1,
        "note_url": "noteUrl-value",
        "note": "note-value",
        "action_url": "actionUrl-value",
        "categories": [2]
      }
      """
    Then the response code should be "201"
    And the JSON should be equal to:
      """
      {
        "id": 15,
        "name": "host_template_name_A",
        "alias": "host-template-alias",
        "snmp_version": "2c",
        "snmp_community": "snmpCommunity-value",
        "timezone_id": 1,
        "severity_id": 1,
        "check_timeperiod_id": 1,
        "note_url": "noteUrl-value",
        "note": "note-value",
        "action_url": "actionUrl-value",
        "is_locked": false,
        "categories": [
          {
            "id": 2,
            "name": "host-cat1"
          }
        ]
      }
      """

    When I send a POST request to '/api/latest/configuration/hosts/templates' with body:
      """
      {
        "name": "host_template name A",
        "alias": "host-template-alias"
      }
      """
    Then the response code should be "409"

    Given the following CLAPI import data:
      """
      HC;ADD;host-cat2;host-cat2-alias
      CONTACT;ADD;ala;ala;ala@localhost.com;Centreon@2022;0;1;en_US;local
      CONTACT;setparam;ala;reach_api;1
      ACLMENU;add;ACL Menu test;my alias
      ACLMENU;grantrw;ACL Menu test;1;Configuration;Hosts;Templates
      ACLRESOURCE;add;ACL Resource test;my alias
      ACLRESOURCE;addfilter_hostcategory;ACL Resource test;host-cat2
      ACLGROUP;add;ACL Group test;my alias
      ACLGROUP;addmenu;ACL Group test;ACL Menu test
      ACLGROUP;addresource;ACL Group test;ACL Resource test
      ACLGROUP;addcontact;ACL Group test;ala
      """
    And I am logged in with "ala"/"Centreon@2022"

    When I send a POST request to '/api/latest/configuration/hosts/templates' with body:
      """
      {
        "name": "  host template name B  ",
        "alias": "  host-template-alias  ",
        "snmp_version": "2c",
        "snmp_community": "   snmpCommunity-value",
        "timezone_id": 1,
        "severity_id": 1,
        "check_timeperiod_id": 1,
        "note_url": "noteUrl-value",
        "note": "note-value",
        "action_url": "actionUrl-value",
        "categories": [2]
      }
      """
    Then the response code should be "409"

    Given the following CLAPI import data:
      """
      ACLRESOURCE;addfilter_hostcategory;ACL Resource test;host-cat1
      """
    And I am logged in with "ala"/"Centreon@2022"

    When I send a POST request to '/api/latest/configuration/hosts/templates' with body:
      """
      {
        "name": "  host template name B  ",
        "alias": "  host-template-alias  ",
        "snmp_version": "2c",
        "snmp_community": "   snmpCommunity-value",
        "timezone_id": 1,
        "severity_id": 1,
        "check_timeperiod_id": 1,
        "note_url": "noteUrl-value",
        "note": "note-value",
        "action_url": "actionUrl-value",
        "categories": [2]
      }
      """
    Then the response code should be "201"
    And the JSON should be equal to:
      """
      {
        "id": 17,
        "name": "host_template_name_B",
        "alias": "host-template-alias",
        "snmp_version": "2c",
        "snmp_community": "snmpCommunity-value",
        "timezone_id": 1,
        "severity_id": 1,
        "check_timeperiod_id": 1,
        "note_url": "noteUrl-value",
        "note": "note-value",
        "action_url": "actionUrl-value",
        "is_locked": false,
        "categories": [
          {
            "id": 2,
            "name": "host-cat1"
          }
        ]
      }
      """