Feature: Delete a Agent Configuration
  As a Centreon user
  I want to visit the Agents Configuration page
  To delete the agent configuration

  Scenario: Delete a agent configuration
    Given a non-admin user is in the Agents Configuration page
    And a agent configuration is already created
    When the user deletes the agent configuration
    And the user confirms on the pop-up
    Then the agent configuration is no longer displayed in the listing page

  Scenario: Cancel a deletion pop-up
    Given a non-admin user is in the Agents Configuration page
    And a agent configuration is already created
    When the user deletes the agent configuration
    And the user cancel on the pop-up
    Then the agent configuration is still displayed in the listing page