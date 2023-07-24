Feature:
  In order to check the service
  As a logged in user
  I want to find service using api

  Background:
    Given a running instance of Centreon Web API
    And the endpoints are described in Centreon Web API documentation

  Scenario: Service delete
    Given the following CLAPI import data:
      """
      CONTACT;ADD;test;test;test@localhost.com;Centreon@2022;0;1;en_US;local
      CONTACT;setparam;test;reach_api;1
      HOST;add;Host-Test;Host-Test-alias;127.0.0.1;;central;
      SERVICE;add;Host-Test;Service-Test;Ping-LAN
      """
    And I am logged in with "test"/"Centreon@2022"

    When I send a DELETE request to '/api/latest/configuration/services/27'
    Then the response code should be "403"

    Given I am logged in
    When I send a DELETE request to '/api/latest/configuration/services/99'
    Then the response code should be "404"

    Then I send a DELETE request to '/api/latest/configuration/services/27'
    Then the response code should be "204"


