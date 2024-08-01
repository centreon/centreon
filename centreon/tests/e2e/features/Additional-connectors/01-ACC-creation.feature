Feature: Create a new Additional Connector Configuration
  As a Centreon user
  I want to visit the Specific Connector Configuration page
  To manage additional connector configuration

  Background:
    Given a non-admin user is logged in
    And the user has Read/Write rights on the Specific Connector Configuration page
    And the user has access to all pollers

  Scenario: Add an additional connector configuration with all informations
    Given a non-admin user is in the Specific Connector Configuration page
    When the user clicks on Add
    Then a pop-up menu with the form is displayed
    When the user fills in all the informations
    And the user clicks on Save
    Then the form is closed
    And the new configuration is displayed in the Specific Connector Configuration page
#    And the parameters should be saved in vault

  Scenario: Add an additional connector configuration with mandatory informations
    Given a non-admin user is in the Specific Connector Configuration page
    When the user clicks on Add
    Then a pop-up menu with the form is displayed
    When the user fills in only the mandatory informations
    And the user clicks on Save
    Then the form is closed
    And the new configuration is displayed in the Specific Connector Configuration page
#    And the parameters should be saved in vault

  Scenario: Add an additional connector configuration with missing informations
    Given a non-admin user is in the Specific Connector Configuration page
    When the user clicks on Add
    Then a pop-up menu with the form is displayed
    When the user doesn't fill in all the mandatory informations
    And the user clicks on Save
    Then the form displayed an error

  Scenario: Add an additional connector configuration with incorrect informations
    Given a non-admin user is in the Specific Connector Configuration page
    When the user clicks on Add
    Then a pop-up menu with the form is displayed
    When the user doesn't fill in correct type of informations
    And the user clicks on Save
    Then the form displayed an error

  Scenario: Cancel a creation form
    Given a non-admin user is in the Specific Connector Configuration page
    When the user clicks on Add
    And the user leaves the creation form without saving
    Then the user is on the Specific Connector Configuration page
    And the additional connector configuration has not been created
    When the user opens the form to create a new additional connector configuration for the second time
    Then the form fields are empty