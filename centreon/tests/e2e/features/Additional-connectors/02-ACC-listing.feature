Feature: Access to Additional Connector Configuration
  As a Centreon user
  I want to be able to visit the Additional Connector Configuration page
  To list Additional Connector Configuration

  Scenario: Access to Additional Connector Configuration page
    Given a non-admin user is logged in
    When the user clicks on the Additional Connector Configuration page
    Then the user sees the Additional Connector Configuration page
    And there is no additional connector configuration listed

  Scenario: Select an additional connector configuration
    Given a non-admin user is in the Additional Connector Configuration page
    And an already existing additional connector configuration
    When the user clicks on the Edit button of the additional connector configuration
    Then a pop up is displayed with all of the additional connector informations