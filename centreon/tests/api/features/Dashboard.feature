Feature:
  In order to use dashboards
  As a user
  I want to get and set dashboard information using api

  Background:
    Given a running instance of Centreon Web API
    And the endpoints are described in Centreon Web API documentation

  Scenario: Dashboard endpoints do NOT found because of disabled feature
    Given I am logged in
    And a feature flag "dashboard" of bitmask 0

    When I send a POST request to '/api/latest/configuration/dashboards' with body:
    """
    { "name": "my-dashboard" }
    """
    Then the response code should be "404"

    When I send a PUT request to '/api/latest/configuration/dashboards/1' with body:
    """
    { "name": "my-dashboard" }
    """
    Then the response code should be "404"

    When I send a GET request to '/api/latest/configuration/dashboards'
    Then the response code should be "404"

    When I send a GET request to '/api/latest/configuration/dashboards/1'
    Then the response code should be "404"

    When I send a DELETE request to '/api/latest/configuration/dashboards/1'
    Then the response code should be "404"

  Scenario: Dashboard add + get with an Administrator
    Given I am logged in
    And a feature flag "dashboard" of bitmask 3
    When I send a POST request to '/api/latest/configuration/dashboards' with body:
    """
    {
       "name": "my-dashboard",
       "description": "my-description"
    }
    """
    Then the response code should be "201"
    And the JSON nodes should be equal to:
      | id          | 1                |
      | name        | "my-dashboard"   |
      | description | "my-description" |
    And the JSON node "created_at" should exist
    And the JSON node "updated_at" should exist
    And I store response values in:
      | name        | path    |
      | dashboardId | id      |

    When I send a GET request to '/api/latest/configuration/dashboards/<dashboardId>'
    Then the response code should be "200"
    And the JSON nodes should be equal to:
      | id          | 1                |
      | name        | "my-dashboard"   |
      | description | "my-description" |
    And the JSON node "created_at" should exist
    And the JSON node "updated_at" should exist

    When I send a GET request to '/api/latest/configuration/dashboards'
    Then the response code should be "200"
    And the JSON nodes should be equal to:
      | result[0].id          | 1                |
      | result[0].name        | "my-dashboard"   |
      | result[0].description | "my-description" |
    And the JSON node "result[0].created_at" should exist
    And the JSON node "result[0].updated_at" should exist

    When I send a GET request to '/api/latest/configuration/dashboards/999'
    Then the response code should be "404"

    When I send a POST request to '/api/latest/configuration/dashboards' with body:
    """
    { "name": "2nd-dashboard" }
    """
    Then the response code should be "201"
    And the JSON nodes should be equal to:
      | id          | 2                 |
      | name        | "2nd-dashboard"   |
      | description | null              |

    When I send a POST request to '/api/latest/configuration/dashboards' with body:
    """
    { "unknown_field": "something" }
    """
    Then the response code should be "400"

    When I send a DELETE request to '/api/latest/configuration/dashboards/999'
    Then the response code should be "404"

    When I send a DELETE request to '/api/latest/configuration/dashboards/2'
    Then the response code should be "204"

    When I send a PUT request to '/api/latest/configuration/dashboards/<dashboardId>' with body:
    """
    { "name": "modified-dashboard-name" }
    """
    Then the response code should be "204"

    When I send a PUT request to '/api/latest/configuration/dashboards/<dashboardId>' with body:
    """
    { "unknown_field": "something" }
    """
    Then the response code should be "400"

    When I send a GET request to '/api/latest/configuration/dashboards/<dashboardId>'
    Then the response code should be "200"
    And the JSON node "name" should be equal to '"modified-dashboard-name"'

    When I send a PUT request to '/api/latest/configuration/dashboards/999' with body:
    """
    { "name": "modified-dashboard-name" }
    """
    Then the response code should be "404"
