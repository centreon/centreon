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