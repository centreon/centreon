@REQ_MON-146653
Feature: Update a Agent Configuration
  As a Centreon user
  I want to visit the Agents Configuration page
  To update the agent agent configuration

  @TEST_MON-151999
  Scenario: Update a agent configuration
    Given a non-admin user is in the Agents Configuration page
    And an already existing agent configuration
    When the user clicks on the line of the agent configuration
    Then a pop up is displayed with all of the agent informations
    When the user updates some informations
    And the user clicks on Save
    Then the form is closed
    And the informations are successfully saved