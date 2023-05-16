Feature:
  In order to check the service templates
  As a logged in user
  I want to find service templates using api

  Background:
    Given a running instance of Centreon Web API
    And the endpoints are described in Centreon Web API documentation

  Scenario: Service templates listing
    Given I am logged in
    And the following CLAPI import data:
    """
    STPL;ADD;service-name-1;service-alias-1;;;;
    """

    When I send a GET request to '/api/latest/configuration/services/templates?search={"name": "service-name-1"}'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 27,
                "name": "service-name-1",
                "alias": "service-alias-1",
                "service_template_id": null,
                "check_timeperiod_id": null,
                "note": null,
                "note_url": null,
                "action_url": null,
                "severity_id": null,
                "is_locked": false
            }
        ],
        "meta": {
            "page": 1,
            "limit": 10,
            "search": {
                "$and": {
                    "name": "service-name-1"
                }
            },
            "sort_by": {},
            "total": 1
        }
    }
    """
