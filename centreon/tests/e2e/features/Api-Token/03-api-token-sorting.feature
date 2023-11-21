@ignore
@REQ_MON24868
Feature: Sorting API Tokens List

  As an administrator
  I want to sort the API tokens list by clicking on a column header
  So that I can organize and view the tokens based on different fields

  Background:
    Given I am logged in as an administrator
    And I am on the API tokens page

  Scenario: Sort tokens by Status
    When I click on the Status column header
    Then the tokens should be sorted by Status in ascending order

  Scenario: Sort tokens by Name
    When I click on the Name column header
    Then the tokens should be sorted by Name in ascending order

  Scenario: Sort tokens by Creator
    When I click on the Creator column header
    Then the tokens should be sorted by Creator in ascending order

  Scenario: Sort tokens by User
    When I click on the User column header
    Then the tokens should be sorted by User in ascending order

  Scenario: Sort tokens by Creation Date
    When I click on the Creation date column header
    Then the tokens should be sorted by Creation date in ascending order

  Scenario: Sort tokens by Expiration Date
    When I click on the Expiration date column header
    Then the tokens should be sorted by Expiration date in ascending order