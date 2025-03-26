Feature: Refresh intervals for the top counter
  As a Centreon user
  I want to check
  That the refresh intervals for the popup counters has effect

  @TEST_MON-157646
  Scenario: Check that Refresh intervals for the top counters has an effect
    Given a user is logged in a Centreon server
    When the user goes to Administration > Parameters > Centreon UI page
    And the user updates the Refresh Interval for statistics field value
    And the user logout from the centreon plateform
    When the user reconnect to the centreon plateform
    Then the top counter refresh request must be called each "defined value" seconds
    And the parameters request must contains the "defined value" for the Refresh Interval for statistics attribut