Feature: Access a Telegraf configuration
  As a Centreon user
  I want to visit the Agents Configuration page
  To list the Telegraf agent configuration

  Scenario: Add a telegraf with all informations
    Given a non-admin user is in the Agents Configuration page
    When the user clicks on Add
    Then a pop-up menu with the form is displayed
    When the user fills in all the informations
    And the user clicks on Create
    Then the first agent is displayed in the Agents Configuration page