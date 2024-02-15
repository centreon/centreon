@ignore
@REQ_MON-21276
Feature: Create and Manage Basic API Token

  As an administrator
  I want to create a functional basic API token using the "Create new token" button
  So that I can manage access to resources and services

  Background:
    Given I am logged in as an administrator
    And I am on the API tokens page

  Scenario Outline: Create Basic API Token with Required Fields
    When I click on the "Create new token" button
    And I fill in the following required fields
    | Field | Value                |
    | Name  | <Enter token name>  |
    | User  | <Enter linked user> |
    And I select the duration as "<Duration>"
    And I click on the "Generate token" button
    Then a new basic API token with hidden display is generated

    Examples:
      | Enter token name | Enter linked user | Duration |
      | TokenName_1      | User_1            | 30d      |
      | TokenName_2      | User_2            | 60d      |
      | TokenName_3      | User_3            | 90d      |

  Scenario: Display and Copy Generated API Token
    Given a basic API token is generated
    When I click to reveal the token
    Then the token is displayed
    And the "copy to clipboard" button is clicked
    And the "copy to clipboard" button is replaced with the check button
    Then the token is successfully copyed

  Scenario: Save Generated API Token
    Given a basic API token is generated
    When I click on the "Save" button
    Then the token is saved successfully

  Scenario: Edit Existing API Token
    Given I am on the API tokens page
    And there is an existing basic API token with the following details:
    | Token Name    | Linked User | Duration |
    | OriginalToken | OriginalUser| 30d      |
    When I click on the token to edit
    And I modify the token details as follows:
    | Token Name      | Linked User | Duration |
    | ModifiedToken   | NewUser     | 60d      |
    And I click on the "Save" button
    Then the token and its modified details are saved successfully
    And the updated token details are displayed correctly on the API tokens page