Feature: Create a new Telegraf configuration
  As a Centreon user
  I want to visit the Agents Configuration page
  To manage the Telegraf agent configuration

  Scenario: Add a telegraf with all informations
    Given a non-admin user is in the Agents Configuration page
    When the user clicks on Add
    Then a pop-up menu with the form is displayed
    When the user fills in all the informations
    And the user clicks on Create
    Then the first agent is displayed in the Agents Configuration page

  Scenario: Add a telegraf with mandatory informations
    Given a non-admin user is in the Agents Configuration page
    When the user clicks on Add
    Then a pop-up menu with the form is displayed
    When the user fills in the mandatory informations
    And the user clicks on Create
    Then the second agent is displayed in the Agents Configuration page

  Scenario: Add a telegraf with missing informations
    Given a non-admin user is in the Agents Configuration page
    When the user clicks on Add
    And the user doesn't fill in all the mandatory informations
    Then the user cannot click on Create

  Scenario: Add a telegraf with incorrect informations
    Given a non-admin user is in the Agents Configuration page
    When the user clicks on Add
    And the user doesn't fill in correct type of informations
    Then the form displayed an error

  Scenario: Cancel a creation form
    Given a non-admin user is in the Agents Configuration page
    When the user clicks on Add
    And the user fills in the needed informations
    And the user clicks on the Cancel button of the creation form
    Then a pop-up appears to confirm cancellation
    And the user confirms the the cancellation
    Then the creation form is closed
    And the telegraf has not been created
    When the user clicks on Add
    Then the form fields are empty