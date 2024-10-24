Feature: Update a Agent Configuration
  As a Centreon user
  I want to visit the Agents Configuration page
  To update the agent agent configuration

  Scenario: Update a agent configuration
    Given a non-admin user is in the Agents Configuration page
    And a agent configuration is already created
    When the user clicks on the Edit properties button of the agent configuration
    Then a pop-up menu with the form is displayed
    And all of the informations of the agent configuration are correct
    When the user updates some information
    And the user clicks on Update
    Then the form is closed
    And the informations are successfully saved