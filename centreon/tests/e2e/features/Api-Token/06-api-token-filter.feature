@ignore
@REQ_MON-24235
Feature: API Token Information Retrieval

  As an administrator or a user with adequate access rights to the "Administration > API token" menu,
  I want to search and retrieve information about API tokens using various filters
  So that I can display only the necessary information.

  Scenario Outline: Filtering API Tokens by '<filter_by>'
    Given a user with access to the API token management page
    When the user filters tokens by '<filter_by>' and clicks on Search
    Then the user should see all tokens with a '<filter_by>' according to the filter
    Examples:
      | filter_by       |
      | Status          |
      | Name            |
      | Creator         |
      | User            |
      | Creation date   |
      | Expiration date |