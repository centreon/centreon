@REQ_MON-152872
Feature: Hosts Severities changes log
  As a Centreon user
  I want to do some changes on hosts severities
  To check if the changes are inserted in to the log page

  @TEST_MON-153302
  Scenario: A call to the endpoint "Add" host severity insert log changes
    Given a user is logged in a Centreon server via APIv2
    When an apiV2 call is made to "Add" a host severity
    Then a new severity is displayed on the hosts severities page
    And a new "ADDED" ligne of log is getting added to the page Administration > Logs
    And the informations of the log are the same as those passed to the endpoint

  @TEST_MON-153303
  Scenario: A call to the endpoint "Delete" a host severity insert log changes
    Given a user is logged in a Centreon server via APIv2
    And a host severity is configured via APIv2
    When an apiV2 call is made to "Delete" the configured host severity
    Then a new "DELETED" ligne of log is getting added to the page Administration > Log

  @TEST_MON-153304
  Scenario: A call to the endpoint "Update" host severity insert log changes
    Given a user is logged in a Centreon server via APIv2
    And a host severity is configured via APIv2
    When an apiV2 call is made to "Update" the parameters of the configured host severity
    Then a new "CHANGED" ligne of log is getting added to the page Administration > Logs
    And the informations of the log are the same as those of the updated host severity

  @TEST_MON-153306
  Scenario: A call to the endpoint "Disable" a host severity insert log changes
    Given a user is logged in a Centreon server via APIv2
    And an enabled host severity is configured via APIv2
    When an apiV2 call is made to "Disable" the configured host severity
    Then a new "DISABLED" ligne of log is getting added to the page Administration > Logs

  @TEST_MON-153307
  Scenario: A call to the endpoint "Enable" a host severity insert log changes
    Given a user is logged in a Centreon server via APIv2
    And a disabled host severity is configured via APIv2
    When an apiV2 call is made to "Enable" the configured host severity
    Then a new "ENABLED" ligne of log is getting added to the page Administration > Logs