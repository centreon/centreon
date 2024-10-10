Feature: Check permissions for the vault
  As a Centreon user
  I want to check if I can access the Vault page
  To manage Vault

  Scenario: Access the vault page with a non-admin user without topology right
    Given a non-admin user without topology right is logged in
    When the user visits the Vault page
    Then the user cannot access the Vault page

  Scenario: Access the vault page with a non-admin user with topology right
    Given a non-admin user with topology right is logged in
    When the user clicks on the Vault page
    Then the user is redirected to the Vault page
    And an error message is displayed to require an admin user
    When the user fills in all the informations
    And the user clicks on Save
    Then an error message is displayed to require an admin user

  Scenario: Display the vault page details
    Given the configuration is already defined
    And a non-admin user with topology right is logged in
    When the user clicks on the Vault page
    Then the user is redirected to the Vault page
    And an error message is displayed to require an admin user
    And the vault fields are empty