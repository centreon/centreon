Feature: ACL Permissions for Administrators

  As an Administrator
  I want to have specific access rights in the ACL menu
  So that I can manage organization tokens and access the API token page

  Background:
    Given I am logged in as an Administrator

  Scenario: Verify "Manage the organization's authentication tokens" action in ACL
    When I navigate to "Administration" > "ACL" > "Actions Access"
    And I click on the "Add" button
    Then I see "Manage the organization's authentication tokens" listed as an action

  Scenario: Verify "API Tokens" Menu Access in ACL
    When I navigate to "Administration" > "ACL" > "Menus Access"
    And I click on the "Add" button
    Then I see "API Tokens" listed under the "Administration" section
