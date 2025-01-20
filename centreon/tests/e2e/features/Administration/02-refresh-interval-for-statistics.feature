Feature: Refresh intervals for the top counter
  As a Centreon user
  I want to check
  That the refresh intervals for the popup counters has effect

  TEST_MON-157646
  Scenario: Check that Refresh intervals for the top counters has an effect
    Given a user is logged in a Centreon server
    When the user goes to Administration > Parameters > Centreon UI page
    And the user updates the Refresh Interval for statistics field value
    And the user clicks on Save
    Then The update is saved
    When the user logout from the centreon plateform
    And the user reconnect
    Then the request of the top counter refresh must be called each "defined value" seconds
    And the request of the parameters must contains the "defined value" for the Refresh Interval for statistics attribut