@REQ_MON-21276
Feature: Create and Manage Basic Authentication Token
  As an administrator
  I want to create a functional basic Authentication token using the "Create new token" button
  So that I can manage access to resources and services

  Background:
    Given I am logged in as an administrator
    And I am on the Authentication tokens page

  @TEST_MON-36696
  Scenario Outline: Create basic Authentication Token with required fields
    When I click on the "Add" button
    And I fill in the following required fields
    | Field | Value               |
    | Name  | <Enter token name>  |
    | User  | <Enter linked user> |
    And I select the duration as "<Duration>"
    And I click on the "Generate token" button
    Then a new basic Authentication token with hidden display is generated

    Examples:
      | Enter token name | Enter linked user | Duration |
      | TokenName_1      | User_1            | 30 days  |
      | TokenName_2      | User_2            | 60 days  |
      | TokenName_3      | User_3            | 90 days  |

  @TEST_MON-36694
  Scenario: Display and copy generated Authentication Token
    Given a basic Authentication token is generated
    When I click to reveal the token
    Then the token is displayed
    And the "copy to clipboard" button is clicked
    Then the token is successfully copied