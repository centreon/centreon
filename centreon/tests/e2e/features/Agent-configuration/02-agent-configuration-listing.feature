Feature: Access a Agent Configuration
  As a Centreon user
  I want to visit the Agents Configuration page
  To list the agent configuration

  Scenario: Access to Agents Configuration page
    Given a non-admin user is logged in
    When the user clicks on the Agents Configuration page
    Then the user sees the Agents Configuration page
    And there is no agent configuration listed

  Scenario: List all informations of a agent configuration
    Given a non-admin user is in the Agents Configuration page
    And an already existing agent configuration
    When the user clicks on the Edit button of the agent configuration
    Then a pop up is displayed with all of the agent informations