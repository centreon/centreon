Feature: Additional Connector Configuration
  As a Centreon user
  I want to check which actions are possible with different rights

  Scenario: Access to Specific Connector Configuration page with an admin user
    Given an admin user is logged in
    And an Additional Connector Configuration already created
    When the admin user accesses the Specific Connector Configuration page
    Then the admin user can view all the additional connector already configured
    When the admin user clicks on the Edit button of the addiction connector configuration
    Then a pop up is displayed with all of the additional connector informations
    And the admin user can update the additional connector configuration

  Scenario: Create a new additional connector configuration with an admin user
    Given an admin user is in the Specific Connector Configuration page
    When the admin user clicks on Add
    Then a pop-up menu with the form is displayed
    When the admin user fills in all the informations
    And the admin user clicks on Save
    Then the form is closed
    And the new configuration is displayed in the Specific Connector Configuration page
#    And the parameters should be saved in vault

  Scenario: Delete an addictional connector configuration with an admin user
    Given an admin user is in the Specific Connector Configuration page
    And an additional connector configuration is already created
    When the admin user deletes the additional connector configuration
    Then the additional connector configuration is no longer displayed in the listing page
#    And its parameters should no longer be saved in vault

#  Scenario: Access to Specific Connector Configuration page with a non-admin user without topology rights
#    Given a non-admin user without topology rights is logged in
#    When the user tries to access the Specific Connector Configuration page
#    Then the user cannot access the Specific Connector Configuration page

  Scenario: Access to Specific Connector Configuration page with a non-admin user with filters on one of the two Pollers
    Given a non-admin user is logged in
    And at least 2 pollers created
    And an Additional Connector Configuration already created linked with the two pollers
    And the user has a filter on one of the pollers
    When the user accesses the Specific Connector Configuration page
    Then the user can not view the additional connector linked to the 2 pollers

  Scenario: Access to Specific Connector Configuration page with a non-admin user with filters on Pollers
    Given a non-admin user is logged in
    And one poller created
    And an Additional Connector Configuration already created linked with only this poller
    And the user has a filter on the poller
    When the user accesses the Specific Connector Configuration page
    Then the user can view the additional connector linked to the poller
    When the user clicks on the Edit button of the addiction connector configuration
    Then a pop up is displayed with all of the additional connector informations
    And the user can update the additional connector configuration

  Scenario: Create a new additional connector configuration with a non-admin user with filters on Pollers
    Given a non-admin user is in the Specific Connector Configuration page
    And an Additional Connector Configuration already created
    And some pollers created
    And the user has a filter on pollers
    When the admin user clicks on Add
    Then a pop-up menu with the form is displayed
    And only the filtered pollers are listed in the Pollers field
    When the admin user fills in all the informations
    And the admin user clicks on Save
    Then the form is closed
    And the new configuration is displayed in the Specific Connector Configuration page
#    And the parameters should be saved in vault

  Scenario: Delete a configuration file with a non-admin user with filters on Pollers
    Given a non-admin user is in the Specific Connector Configuration page
    And an Additional Connector Configuration already created
    And some pollers created
    And the user has a filter on pollers
    When the user deletes the additional connector configuration
    Then the additional connector configuration is no longer displayed in the listing page
#    And its parameters should no longer be saved in vault