@REQ_MON-144628
Feature: Delete an Additional Connector Configuration
  As a Centreon user
  I want to visit the Additional Connector Configuration page
  To delete additional connector configuration

  @TEST_MON-150326
  Scenario: Delete an ACC
    Given a non-admin user is in the Additional Connector Configuration page
    And an additional connector configuration is already created
    When the user deletes the additional connector configuration
    And the user confirms on the pop-up
    Then the additional connector configuration is no longer displayed in the listing page

  @TEST_MON-150327
  Scenario: Cancel a deletion pop-up
    Given a non-admin user is in the Additional Connector Configuration page
    And an additional connector configuration is already created
    When the user deletes the additional connector configuration
    And the user cancel on the pop-up
    Then the additional connector configuration is still displayed in the listing page