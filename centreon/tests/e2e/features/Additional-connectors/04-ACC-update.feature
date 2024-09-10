Feature: Additional Connector Configuration
  As a Centreon user
  I want to visit the Specific Connector Configuration page
  To update additional connector configuration

  Scenario: Update an additional connector configuration
    Given a non-admin user is in the Specific Connector Configuration page
    And an additional connector configuration is already created
    When the user clicks on the Edit properties button of an additional connector configuration
    Then a pop-up menu with the form is displayed
    And all of the informations of the additional connector configuration are correct
    When the user udpates some informations
    And the user clicks on Update
    Then the form is closed
    And the informations are successfully saved