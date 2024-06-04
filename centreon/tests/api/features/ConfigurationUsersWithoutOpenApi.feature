Feature:
  In order to get information on the current user
  As a user
  I want retrieve those information

  Background:
    Given a running instance of Centreon Web API
    # Commented by purpose here because we want to test the real JSON Schema
    # And the endpoints are described in Centreon Web API documentation

  Scenario: Test non Open Api valid values
    Given I am logged in

    When I send a PATCH request to '/api/latest/configuration/users/current/parameters' with body:
    """
    { "theme": "this_is_not_valid" }
    """
    Then the response code should be "500"

    When I send a PATCH request to '/api/latest/configuration/users/current/parameters' with body:
    """
    { "user_interface_density": "this_is_not_valid" }
    """
    Then the response code should be "500"

