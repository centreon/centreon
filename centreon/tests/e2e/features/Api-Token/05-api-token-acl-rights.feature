@ignore
@REQ_MON24872
Feature: ACL Permissions for Administrators

  As an Administrator
  I want to have specific access rights in the ACL menu
  So that I can manage organization tokens and access the API token page

  Background:
    Given I am logged in as an Administrator
    And I am on the ACL menu

  Scenario: Verify "Manage Organization Token" Menu Action in ACL
    When I navigate to "Administration" > "ACL" > "Menus Actions"
    Then I should see "Manage organization token" listed as an action

  Scenario: Verify "API Token" Menu Access in ACL
    When I navigate to "Administration" > "Menus Access"
    Then I should see "API token" listed under the "Administration" section