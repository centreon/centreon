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

  Scenario: Host template creation
    Given I am logged in

    When I send a POST request to '/api/latest/configuration/hosts/templates' with body:
      """
      {
        "name": "  host template name  ",
        "alias": "  host-template-alias  ",
        "snmp_version": "2c",
        "snmp_community": "   snmpCommunity-value",
        "timezone_id": 1,
        "severity_id": 1,
        "note_url": 'noteUrl-value',
        "note": 'note-value',
        "action_url": 'actionUrl-value',
        "is_activated": false
      }
      """
    Then the response code should be "201"
    And the JSON should be equal to:
      """
      {
        "id": 15,
        "name": "host_template_name",
        "alias": "host-template-alias",
        "snmp_version": "2c",
        "snmp_community": "snmpCommunity-value",
        "timezone_id": 1,
        "severity_id": 1,
        "note_url": 'noteUrl-value',
        "note": 'note-value',
        "action_url": 'actionUrl-value',
        "is_activated": false,
        "is_locked": false
      }
      """

    # conflict on name
    When I send a POST request to '/api/latest/configuration/hosts/templates' with body:
      """
      {
        "name": "host_template name",
        "alias": "host-template-alias",
        "is_activated": true
      }
      """
    Then the response code should be "409"

    # missing mandatory fields
    When I send a POST request to '/api/latest/configuration/hosts/templates' with body:
      """
      { "not_exists": "foo-bar" }
      """
    Then the response code should be "400"
    And the JSON should be equal to:
      """
      {
        "code": 400,
        "message": "[name] The property name is required\n[alias] The property alias is required\nThe property not_exists is not defined and the definition does not allow additional properties\n"
      }
      """
