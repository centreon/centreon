Feature:
  In order to check the time period configuration
  As a logged in user
  I want to check all the API endpoints of time periods

  Background:
    Given a running instance of Centreon Web API

  Scenario: Time period listing
    Given I am logged in
    When I send a GET request to '/api/latest/configuration/timeperiods'
    Then the response code should be "200"