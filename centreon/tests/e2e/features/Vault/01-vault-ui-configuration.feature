Feature: Configure a vault
  As a Centreon user
  I want to visit the Vault page
  To manage Vault

  Scenario: Reset a creation form
    Given an admin user is in the Vault page
    When the user fills in all the informations
    And the user clicks on the Reset button
    Then a pop-up appears to confirm the reset
    When the user confirms the reset
    Then the vault configuration fields are empty

  Scenario: Add a first Vault configuration
    Given an admin user is logged in
    When the user clicks on the Vault page
    Then the user is redirected to the Vault page
    When the user fills in all the informations
    And the user clicks on Save
    Then the vault is successfully saved
    And the informations are displayed

  Scenario: Add a Vault configuration with missing informations
    Given an admin user is in the Vault page
    When the user doesn't fill in all the informations
    Then the user cannot click on Save

  Scenario: Add a Vault configuration with incorrect informations
    Given an admin user is in the Vault page
    When the user doesn't fill in the correct informations
    And the user clicks on Save
    Then the form displayed an error for invalid configuration

  Scenario: Display migration command
    Given an admin user is in the Vault page
    And the configuration is already defined
    When the user clicks on the Migrate button
    Then a pop-up appears with the migration informations