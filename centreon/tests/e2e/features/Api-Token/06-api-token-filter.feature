@REQ_MON-24235
Feature: API Token Information Retrieval

  As an administrator or a user with adequate access rights to the "Administration > API token" menu,
  I want to search and retrieve information about API tokens using various filters
  So that I can display only the necessary information.

  Background:
    Given I am logged in as an administrator
    And API tokens with predefined details are created
    And I am on the API tokens page

  @TEST_MON-36705
  Scenario Outline: Filtering API Tokens by '<filter_by>'
    When I filter tokens by '<filter_by>' and click on Search
    Then I should see all tokens with a '<filter_by>' according to the filter
    Examples:
      | filter_by       |
      # | Status          |
      | Name            |
      | Creator         |
      | User            |
      # | Creation date   |
      # | Expiration date |