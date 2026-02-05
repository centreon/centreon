@REQ_MON-152872
Feature: Services categories changes log
  As a Centreon user
  I want to do some changes on services categories
  To check if the changes are inserted in to the log page

  @TEST_MON-153465
  Scenario: A call to the endpoint "Add" a service category insert log changes
    Given a user is logged in a Centreon server via APIv2
    When an apiV2 call is made to "Add" a service category
    Then a new service category is displayed on the service categories page
    And a new "ADDED" line of log is getting added to the page Administration > Logs
    And the informations of the log are the same as those passed to the endpoint

  @TEST_MON-153466
  Scenario: A call to the endpoint "Delete" a service category insert log changes
    Given a user is logged in a Centreon server via APIv2
    And a service category is configured via APIv2
    When an apiV2 call is made to "Delete" the configured service category
    Then a new "DELETED" line of log is getting added to the page Administration > Log

  @TEST_MON-153467
  Scenario: A call to the endpoint "Update" service category insert log changes
    Given a user is logged in a Centreon server via APIv2
    And a service category is configured via APIv2
    When the user changes some properties of the configured service category from UI
    Then a new "CHANGED" line of log is getting added to the page Administration > Logs
    And the informations of the log are the same as the changed properties

  @TEST_MON-153468
  Scenario: A call to the endpoint "Disable" a service category insert log changes
    Given a user is logged in a Centreon server via APIv2
    And an enabled service category is configured via APIv2
    When the user disables the configured service category from UI
    Then a new "DISABLED" line of log is getting added to the page Administration > Logs

  @TEST_MON-153469
  Scenario: A call to the endpoint "Enable" a service category insert log changes
    Given a user is logged in a Centreon server via APIv2
    And a disabled service category is configured via APIv2
    When the user enables the configured service category from UI
    Then a new "ENABLED" line of log is getting added to the page Administration > Logs