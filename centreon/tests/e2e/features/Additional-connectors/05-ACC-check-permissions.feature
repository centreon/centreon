Feature: Additional Connector Configuration
  As a Centreon user
  I want to check which actions are possible with different rights

  Scenario: Create a new additional connector configuration with an admin user
    Given an admin user is in the Specific Connector Configuration page
    When the admin user clicks on Add
    Then a pop-up menu with the form is displayed
    When the admin user fills in all the informations
    And the admin user clicks on Save
    Then the form is closed
    And the first configuration is displayed in the Specific Connector Configuration page
#    And the parameters should be saved in vault

  Scenario: Update an additional connector configuration page with an admin user
    Given an admin user is in the Specific Connector Configuration page
    And an additional connector configuration is already created
    When the user clicks on the Edit button of the addiction connector configuration
    Then a pop up is displayed with all of the additional connector informations
    And the admin user can update the additional connector configuration

  Scenario: Delete an additional connector configuration with an admin user
    Given an admin user is in the Specific Connector Configuration page
    And an additional connector configuration is already created
    When the admin user deletes the additional connector configuration
    Then the additional connector configuration is no longer displayed in the listing page
  #  And its parameters should no longer be saved in vault

 Scenario: Access to Specific Connector Configuration page with a non-admin user without topology rights
   Given a non-admin user without topology rights is logged in
   When the user tries to access the Specific Connector Configuration page
   Then the user cannot access the Specific Connector Configuration page

  Scenario: Access to Specific Connector Configuration page with a non-admin user with filters on Pollers
    Given a non-admin user is logged in
    And an Additional Connector Configuration already created linked with two pollers
    And the user has a filter on one of the pollers
    When the user accesses the Specific Connector Configuration page
    Then the user can not view the additional connector linked to the 2 pollers
    When the admin user updates the filtered pollers of the non-admin user
    Then the user can view the additional connector linked to the pollers
    When the user clicks on the Edit button of the addiction connector configuration
    Then a pop up is displayed with all of the additional connector informations and the 2 pollers
    And the user can update the additional connector configuration

  Scenario: Create a new additional connector configuration with a non-admin user with filters on Pollers
    Given a non-admin user is in the Specific Connector Configuration page
    And an additional connector configuration is already created
    When the user adds a second additional connector configuration
    Then only the free filtered pollers are listed in the Pollers field
    When the non-admin user fills in all the informations
    Then the form is closed
    And the new configuration is displayed in the Specific Connector Configuration page
#    And the parameters should be saved in vault

  Scenario: Delete a configuration file with a non-admin user with filters on Pollers
    Given a non-admin user is in the Specific Connector Configuration page
    And an additional connector configuration is already created
    When the user deletes the additional connector configuration
    Then the additional connector configuration is no longer displayed in the listing page
#    And its parameters should no longer be saved in vault