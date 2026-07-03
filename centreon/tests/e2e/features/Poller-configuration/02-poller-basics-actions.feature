@REQ_MON-22134
Feature: Generate poller configuration
  As a Centreon user
  I want to do some basics actions on pollers

  @MON-198192
  Scenario: Duplicate an existing remote poller
    Given an admin user is logged in a Centreon server
    And a remote poller is configured
    When the user duplicates the configured poller
    Then a new disabled poller is created with identical properties
    When the user exports the configuration
    Then a success message is displayed