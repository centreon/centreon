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
    Then a new basic API token with hidden display should be generated

    Examples:
      | Duration |
      | 30d      |
      | 60d      |
      | 90d      |

  Scenario: Display and Copy Generated Token
    Given a basic API token is generated
    When I click to reveal the token
    Then the token is displayed
    And the "copy to clipboard" button is clicked

  Scenario: Save Generated Token
    Given a basic API token is generated
    When I click on the "Save" button
    Then the token is saved successfully

  Scenario: Edit Existing Token
    Given I am on the API tokens page
    And there is an existing basic API token
    When I click on the token to edit
    And I modify the token details
    And I click on the "Save" button
    Then the token is updated successfully