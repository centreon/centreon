Feature: Testing a configuration proxy
  As a Centreon user
  I want to test my proxy configuration
  So that to verify it

  @TEST_MON-159642
  Scenario: Proxy settings with a correct connection
    Given a user is logged in a Centreon server with a configured proxy
    When the user tests the proxy configuration in the interface
    Then a popin displays a successful connexion

  @TEST_MON-159643
  Scenario: Proxy settings with a wrong connection
    Given a user is logged in a Centreon server with a wrongly configured proxy
    When the user tests the proxy configuration in the interface
    Then a popin displays an error message