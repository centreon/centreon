Feature:
  In order to use dashboards
  As a user
  I want to test the JSON Schema of the controller.

  Background:
    Given a running instance of Centreon Web API
    # Commented by purpose here because we want to test the real JSON Schema
    # And the endpoints are described in Centreon Web API documentation

  Scenario: Dashboard add + get with an Administrator
    Given I am logged in
    And a feature flag "dashboard" of bitmask 3

    When I send a POST request to '/api/latest/configuration/dashboards' with body:
    """
    { "unknown_field": "something" }
    """
    Then the response code should be "400"

  Scenario: Dashboard contact access rights with an Administrator
    Given I am logged in
    And a feature flag "dashboard" of bitmask 3

    When I send a POST request to '/api/latest/configuration/dashboards' with body:
    """
    { "name": "my-dashboard" }
    """
    Then the response code should be "201"
    And I store response values in:
      | name        | path |
      | dashboardId | id   |

    When I send a POST request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contacts' with body:
    """
    { "unknown_field": "something" }
    """
    Then the response code should be "400"

    When I send a POST request to '/api/latest/configuration/dashboards/<dashboardId>/access_rights/contacts' with body:
    """
    { "id": 1, "role": "not_exist" }
    """
    Then the response code should be "400"
