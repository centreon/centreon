@ignore
@REQ_MON-21279
Feature: Sorting API Tokens List

  As an administrator
  I want to sort the API tokens list by clicking on a column header
  So that I can organize and view the tokens based on different fields

  Background:
    Given I am logged in as an administrator
    And I am on the API tokens page

  Scenario Outline: Sort tokens by '<order_by>'
    When I click on the '<order_by>' column header
    Then the tokens are sorted by '<order_by>' in ascending order
    Examples:
      | order_by        |
      | Status          |
      | Name            |
      | Creator         |
      | User            |
      | Creation date   |
      | Expiration date |