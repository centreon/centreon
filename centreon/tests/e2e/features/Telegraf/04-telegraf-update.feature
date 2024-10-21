Feature: Update a Telegraf configuration
  As a Centreon user
  I want to visit the Agents Configuration page
  To update the Telegraf agent configuration

  Scenario: Update a telegraf configuration
    Given a non-admin user is in the Agents Configuration page
    And a telegraf configuration is already created
    When the user clicks on the Edit properties button of the telegraf configuration
    Then a pop-up menu with the form is displayed
    And all of the informations of the telegraf configuration are correct
    When the user updates some information
    And the user clicks on Update
    Then the form is closed
    And the informations are successfully saved