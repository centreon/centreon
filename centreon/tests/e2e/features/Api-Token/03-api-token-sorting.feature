@REQ_MON-21279
Feature: Sorting API Tokens List

  As an administrator
  I want to sort the API tokens list by clicking on a column header
  So that I can organize and view the tokens based on different fields

  Background:
    Given I am logged in as an administrator
    And API tokens with predefined details are created
    And I am on the API tokens page

  @TEST_MON-36701
  Scenario Outline: Sort tokens by '<order_by>'
    When I click on the '<order_by>' column header
    Then the tokens are sorted by '<order_by>' in descending order
    Examples:
      | order_by        |
      | Name            |
      | Creator         |
      | User            |
      | Creation Date   |
      | Expiration Date |
