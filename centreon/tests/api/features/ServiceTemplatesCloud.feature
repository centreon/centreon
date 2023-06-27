Feature:
  In order to check the service templates
  As a logged in user
  I want to find service templates using api

  Background:
    Given a running cloud platform instance of Centreon Web API
    And the endpoints are described in Centreon Web API documentation

  Scenario: Service templates listing
    Given I am logged in
    And the following CLAPI import data:
    """
    CONTACT;ADD;test;test;test@localhost.com;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;test;reach_api;1
    """

    When I send a POST request to '/api/latest/configuration/services/templates' with body:
    """
    {
        "name": "service template test",
        "alias": "service template alias",
        "service_template_id": 1,
        "check_timeperiod_id": 1,
        "note": "note",
        "note_url": "note_url",
        "action_url": "action url",
        "severity_id": null,
        "host_templates": [3, 11]
    }
    """
    Then the response code should be 201
    And the JSON should be equal to:
    """
    {
        "id": 27,
        "name": "service template test",
        "alias": "service template alias",
        "service_template_id": 1,
        "check_timeperiod_id": 1,
        "note": "note",
        "note_url": "note_url",
        "action_url": "action url",
        "severity_id": null,
        "host_templates": [
            3,
            11
        ],
        "is_locked": false
    }
    """

    When I send a GET request to '/api/latest/configuration/services/templates?search={"name": "service template test"}'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 27,
                "name": "service template test",
                "alias": "service template alias",
                "service_template_id": 1,
                "check_timeperiod_id": 1,
                "note": "note",
                "note_url": "note_url",
                "action_url": "action url",
                "severity_id": null,
                "host_templates": [
                    3,
                    11
                ],
                "is_locked": false
            }
        ],
        "meta": {
            "page": 1,
            "limit": 10,
            "search": {
                "$and": {
                    "name": "service template test"
                }
            },
            "sort_by": {},
            "total": 1
        }
    }
    """

    When I send a PATCH request to '/api/latest/configuration/services/templates/27' with body:
    """
    {
        "host_templates": [2, 3]
    }
    """
    Then the response code should be "204"

    When I send a GET request to '/api/latest/configuration/services/templates?search={"name": "service template test"}'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 27,
                "name": "service template test",
                "alias": "service template alias",
                "service_template_id": 1,
                "check_timeperiod_id": 1,
                "note": "note",
                "note_url": "note_url",
                "action_url": "action url",
                "severity_id": null,
                "host_templates": [
                    2,
                    3
                ],
                "is_locked": false
            }
        ],
        "meta": {
            "page": 1,
            "limit": 10,
            "search": {
                "$and": {
                    "name": "service template test"
                }
            },
            "sort_by": {},
            "total": 1
        }
    }
    """

    Given I am logged in with "test"/"Centreon@2022"
    When I send a DELETE request to '/api/v23.10/configuration/services/templates/27'
    Then the response code should be "403"

    When I am logged in
    Then I send a DELETE request to '/api/v23.10/configuration/services/templates/27'
    Then the response code should be "204"

    When I send a GET request to '/api/v23.10/configuration/services/templates?search={"name": "service template test"}'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [],
        "meta": {
            "page": 1,
            "limit": 10,
            "search": {
                "$and": {
                    "name": "service template test"
                }
            },
            "sort_by": {},
            "total": 0
        }
    }
    """
