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
