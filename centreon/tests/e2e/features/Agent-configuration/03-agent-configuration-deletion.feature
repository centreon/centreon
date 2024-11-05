@REQ_MON-146653
Feature: Delete an Agent Configuration
  As a Centreon user
  I want to visit the Agents Configuration page
  To delete the agent configuration

  @TEST_MON-151997
  Scenario: Delete an agent configuration
    Given a non-admin user is in the Agents Configuration page
    And an already existing agent configuration
    When the user deletes the agent configuration
    And the user confirms on the pop-up
    Then the agent configuration is no longer displayed in the listing page

  @TEST_MON-151998
  Scenario: Cancel a deletion pop-up
    Given a non-admin user is in the Agents Configuration page
    And an already existing agent configuration
    When the user deletes the agent configuration
    And the user cancel on the pop-up
    Then the agent configuration is still displayed in the listing page