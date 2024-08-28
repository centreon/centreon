Feature: Delete an Additional Connector Configuration
  As a Centreon user
  I want to visit the Specific Connector Configuration page
  To delete additional connector configuration

  Scenario: Delete a configuration file
    Given a non-admin user is in the Specific Connector Configuration page
    And an additional connector configuration is already created
    When the user deletes the additional connector configuration
    Then the additional connector configuration is no longer displayed in the listing page
#    And its parameters should no longer be saved in vault