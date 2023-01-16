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

  Scenario: Host group add with minimal payload as an Administrator
    Given I am logged in
    When I send a POST request to '/api/latest/configuration/hosts/groups' with body:
    """
    {
        "name": "test-add"
    }
    """
    Then the response code should be "201"
    And the JSON should be equal to:
    """
    {
        "id": 62,
        "name": "test-add",
        "alias": null,
        "icon_id": null,
        "geo_coords": null,
        "is_activated": true
    }
    """

  Scenario: Host group add with an invalid payload as an Administrator
    Given I am logged in
    When I send a POST request to '/api/latest/configuration/hosts/groups' with body:
    """
    {
        "not_existing": "Hello World"
    }
    """
    Then the response code should be "400"

  Scenario: Host group add with full payload as an Administrator
    Given I am logged in
    When I send a POST request to '/api/latest/configuration/hosts/groups' with body:
    """
    {
        "name": "test-add1",
        "alias": "test-alias",
        "icon_id": 1,
        "geo_coords": "-2,+3",
        "is_activated": true
    }
    """
    Then the response code should be "201"
    And the JSON should be equal to:
    """
    {
        "id": 62,
        "name": "test-add1",
        "alias": "test-alias",
        "icon_id": 1,
        "geo_coords": "-2,+3",
        "is_activated": true
    }
    """
    When I send a POST request to '/api/latest/configuration/hosts/groups' with body:
    """
    {
        "name": "test-add2",
        "alias": "",
        "icon_id": 1,
        "geo_coords": "",
        "is_activated": true
    }
    """
    Then the response code should be "201"
    And the JSON should be equal to:
    """
    {
        "id": 63,
        "name": "test-add2",
        "alias": null,
        "icon_id": 1,
        "geo_coords": null,
        "is_activated": true
    }
    """
    When I send a POST request to '/api/latest/configuration/hosts/groups' with body:
    """
    {"name": "test-add2"}
    """
    Then the response code should be "409"

  Scenario: Host group add with unknown fields for the cloud platform
    Given I am logged in
    When I send a POST request to '/api/latest/configuration/hosts/groups' with body:
    """
    {
        "name": "test-add",
        "notes": "test-notes"
    }
    """
    Then the response code should be "400"
    When I send a POST request to '/api/latest/configuration/hosts/groups' with body:
    """
    {
        "name": "test-add",
        "notes_url": "test-notes_url"
    }
    """
    Then the response code should be "400"
    When I send a POST request to '/api/latest/configuration/hosts/groups' with body:
    """
    {
        "name": "test-add",
        "action_url": "test-action_url"
    }
    """
    Then the response code should be "400"
    When I send a POST request to '/api/latest/configuration/hosts/groups' with body:
    """
    {
        "name": "test-add",
        "icon_map_id": 1
    }
    """
    Then the response code should be "400"
    When I send a POST request to '/api/latest/configuration/hosts/groups' with body:
    """
    {
        "name": "test-add",
        "rrd": 88
    }
    """
    Then the response code should be "400"
    When I send a POST request to '/api/latest/configuration/hosts/groups' with body:
    """
    {
        "name": "test-add",
        "comment": "test-comment"
    }
    """
    Then the response code should be "400"
