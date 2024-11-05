@REQ_MON-146653
Feature: Check permissions on Agent Configuration
  As a Centreon user
  I want to check which actions are possible with different rights

  @TEST_MON-152000
  Scenario: Create an agent configuration with an admin user
    Given an admin user is in the Agents Configuration page
    When the user clicks on Add
    Then a pop-up menu with the form is displayed
    When the admin user fills in all the information
    And the user clicks on Save
    Then the creation form is closed
    And the first configuration is displayed in the Agents Configuration page

  @TEST_MON-152001
  Scenario: Update an agent configuration with an admin user
    Given an admin user is in the Agents Configuration page
    And an agent configuration is already created
    When the user clicks on the line of the Agents Configuration
    Then a pop up is displayed with all of the agent information
    When the user modifies the configuration
    And the user clicks on Save
    Then the update form is closed
    And the updated configuration is displayed correctly in the Agents Configuration page

  @TEST_MON-152002
  Scenario: Delete an agent configuration with an admin user
    Given an admin user is in the Agents Configuration page
    And an agent configuration is already created
    When the user deletes the Agents Configuration
    Then the Agents Configuration is no longer displayed in the listing page

  @TEST_MON-152003
  Scenario: Access to Agents Configuration page with a non-admin user without topology rights
    Given a non-admin user without topology rights is logged in
    When the user tries to access the Agents Configuration page
    Then the user cannot access the Agents Configuration page

  @TEST_MON-152004
  Scenario: Access to Agents page with a non-admin user with filters on Pollers
    Given a non-admin user is logged in
    And an agent configuration already created linked with two pollers
    And the user has a filter on one of the pollers
    When the user accesses the Agents Configuration page
    Then the user can not view the agent configuration linked to the 2 pollers
    When the admin user updates the filtered pollers of the non-admin user
    Then the user can view the agent configuration linked to the pollers
    When the user clicks on the line of the Agents Configuration
    Then a pop up is displayed with all of the agent configuration information with the 2 pollers
    And the user can update the Agents Configuration

  @TEST_MON-152005
  Scenario: Create an agent configuration with a non-admin user with filters on Pollers
    Given a non-admin user is in the Agents Configuration page
    And an already existing agent configuration is displayed
    When the user adds a second agent configuration
    Then only the filtered pollers are listed in the Pollers field
    When the non-admin user fills in all the information
    And the user clicks on Save
    Then the creation form is closed
    And the second configuration is displayed in the Agents Configuration page

  @TEST_MON-152006
  Scenario: Delete an agent configuration with a non-admin user with filters on Pollers
    Given a non-admin user is in the Agents Configuration page
    And an already existing agent configuration is displayed
    When the user deletes the Agents Configuration
    Then the first Agents Configuration is no longer displayed in the listing page