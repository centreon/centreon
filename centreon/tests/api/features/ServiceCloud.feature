Feature:
  In order to check the service
  As a logged in user
  I want to find service using api

  Background:
    Given a running cloud platform instance of Centreon Web API
    And the endpoints are described in Centreon Web API documentation

  Scenario: Service delete
    Given I am logged in
    And the following CLAPI import data:
    """
    HOST;add;Host-Test;Host-Test-alias;127.0.0.1;;central;
    SERVICE;add;Host-Test;ping;
    """

    When I send a DELETE request to '/api/v23.10/configuration/services/99'
    Then the response code should be "403"

    Then I send a DELETE request to '/api/v23.10/configuration/services/27'
    Then the response code should be "204"
