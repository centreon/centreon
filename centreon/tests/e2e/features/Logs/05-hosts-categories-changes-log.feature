@REQ_MON-152872
Feature: Hosts categories changes log
  As a Centreon user
  I want to do some changes on hosts categories
  To check if the changes are inserted in to the log page

  @TEST_MON-153881
  Scenario: A call to the endpoint "Add" a host category insert log changes
    Given a user is logged in a Centreon server via APIv2
    When an apiV2 call is made to "Add" a host category
    Then a new host category is displayed on the host categories page
    And a new "Added" line of log is getting added to the page Administration > Logs
    And the informations of the log are the same as those passed to the endpoint

  @TEST_MON-153882
  Scenario: A call to the endpoint "Delete" a host category insert log changes
    Given a user is logged in a Centreon server via APIv2
    And a host category is configured via APIv2
    When an apiV2 call is made to "Delete" the configured host category
    Then a new "Deleted" line of log is getting added to the page Administration > Log

  @TEST_MON-153883
  Scenario: A call to the endpoint "Update" a host category insert log changes
    Given a user is logged in a Centreon server via APIv2
    And a host category is configured via APIv2
    When an APIv2 call is made to "Update" the configured host category
    Then a new "Changed" line of log is getting added to the page Administration > Logs
    And the informations of the log are the same as those passed to te "PUT" api call

  @TEST_MON-153884
  Scenario: A call to the endpoint "Disable" a host category insert log changes
    Given a user is logged in a Centreon server via APIv2
    And an enabled host category is configured via APIv2
    When an APIv2 call is made to "Disable" the configured host category
    Then a new "DISABLED" line of log is getting added to the page Administration > Logs

  @TEST_MON-153885
  Scenario: A call to the endpoint "Enable" a host category insert log changes
    Given a user is logged in a Centreon server via APIv2
    And a disabled host category is configured via APIv2
    When an APIv2 call is made to "Enable" the disabled host category
    Then a new "ENABLED" line of log is getting added to the page Administration > Logs