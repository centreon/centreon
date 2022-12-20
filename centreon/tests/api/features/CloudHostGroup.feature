Feature:
  In order to monitor hosts by groups
  As a user
  I want to get host group information using api

  Background:
    Given a running cloud platform instance of Centreon Web API
    And the endpoints are described in Centreon Web API documentation

  Scenario: Host group listing with an Administrator
    Given I am logged in
    And the following CLAPI import data:
    """
    HG;ADD;Test Host Group;Alias Test host group
    """

    When I send a GET request to '/api/latest/configuration/hosts/groups?search={"name": {"$eq": "Test Host Group"}}'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 62,
                "name": "Test Host Group",
                "alias": "Alias Test host group",
                "icon_id": null,
                "geo_coords": null,
                "is_activated": true
            }
        ],
        "meta": {
            "page": 1,
            "limit": 10,
            "search": {
                "$and": {"name": {"$eq": "Test Host Group"}}
            },
            "sort_by": {},
            "total": 1
        }
    }
    """
