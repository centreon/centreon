@REQ_MON-152872
Feature: Time Periods changes log
  As a Centreon user
  I want to do some changes on time periods
  To check if the changes are inserted in to the log page

  @TEST_MON-152875
  Scenario: A call to the endpoint "Create" a timeperiod insert log changes
    Given a user is logged in a Centreon server via APIv2
    When a call to the endpoint "Add" a time period is done via APIv2
    Then a new time period is displayed on the time periods page
    And a new "Added" ligne of log is getting added to the page Administration > Logs
    And the informations of the log are the same as those passed to the endpoint

  @TEST_MON-152877
  Scenario: A call to the endpoint "Update" a timeperiods insert log changes
    Given a user is logged in a Centreon server via APIv2
    And a time period is configured via APIv2
    When a call to the endpoint "Update" a time period is done on the configured time period via APIv2
    Then a new "Changed" ligne of log is getting added to the page Administration > Logs
    And the informations of the log are the same as those of the updated time period

  @TEST_MON-152876
  Scenario: A call to the endpoint "Delete" a timeperiods insert log changes
    Given a user is logged in a Centreon server via APIv2
    And a time period is configured via APIv2
    When a call to the endpoint "Delete" a time period is done on the configured time period via APIv2
    Then a new "Deleted" ligne of log is getting added to the page Administration > Logs