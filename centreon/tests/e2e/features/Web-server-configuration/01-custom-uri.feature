@REQ_MON-22146
Feature: Change the base URI
    As an admin
    I want to update the centreon base URI
    So that my platform uses my company's own domain name

  @TEST_MON-22147
  Scenario: Change the base URI
    When I update the base uri within the corresponding web server configuration file
    And I reload the web server
    Then I can authenticate to the centreon platform
    And the resource icons are displayed in configuration and monitoring pages
    And the detailed information of the monitoring resources are displayed