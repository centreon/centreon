@REQ_MON-38501
Feature: ACL Permissions for Administrators

  As an Administrator
  I want to have specific access rights in the ACL menu
  So that I can manage organization tokens and access the API token page

  Background:
    Given I am logged in as an Administrator

  @TEST_MON-38420
  Scenario: Verify "Manage API tokens" action in ACL
    When I navigate to "Administration" > "ACL" > "Actions Access"
    And I click on the "Add" button
    Then I see "Manage API tokens" listed as an action

  @TEST_MON-38421
  Scenario: Verify "API Tokens" Menu Access in ACL
    When I navigate to "Administration" > "ACL" > "Menus Access"
    And I click on the "Add" button
    Then I see "API Tokens" listed under the "Administration" section
