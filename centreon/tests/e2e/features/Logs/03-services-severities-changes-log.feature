@REQ_MON-152872
Feature: Services severities changes log
  As a Centreon user
  I want to do some changes on services severities
  To check if the changes are inserted in to the log page

  @TEST_MON-153431
  Scenario: A call to the endpoint "Add" a service severity insert log changes
    Given a user is logged in a Centreon server via APIv2
    When an apiV2 call is made to "Add" a service severity
    Then a new service severity is displayed on the service severities page
    And a new "Added" ligne of log is getting added to the page Administration > Logs
    And the informations of the log are the same as those passed to the endpoint

  @TEST_MON-153434
  Scenario: A call to the endpoint "Delete" a service severity insert log changes
    Given a user is logged in a Centreon server via APIv2
    And a service severity is configured via APIv2
    When an apiV2 call is made to "Delete" the configured service severity
    Then a new "Deleted" ligne of log is getting added to the page Administration > Log

  @TEST_MON-153435
  Scenario: A call to the endpoint "Update" service severity insert log changes
    Given a user is logged in a Centreon server via APIv2
    And a service severity is configured via APIv2
    When an apiV2 call is made to "Update" the parameters of the configured severity
    Then a new "Changed" ligne of log is getting added to the page Administration > Logs
    And the informations of the log are the same as those of the updated service severity

  @TEST_MON-153436
  Scenario: A call to the endpoint "Disable" a service severity insert log changes
    Given a user is logged in a Centreon server via APIv2
    And an enabled service severity is configured via APIv2
    When an apiV2 call is made to "Disable" the configured service severity
    Then a new "DISABLED" ligne of log is getting added to the page Administration > Logs

  @TEST_MON-153438
  Scenario: A call to the endpoint "Enable" a service severity insert log changes
    Given a user is logged in a Centreon server via APIv2
    And a disabled service severity is configured via APIv2
    When an apiV2 call is made to "Enable" the configured service severity
    Then a new "ENABLED" ligne of log is getting added to the page Administration > Logs