Feature: List Notification Rules
  As a Centreon user with access to the Notification Rules page
  The user want to view the list of notification rules
  So that the user can effectively organize my alerts in the monitoring system

  Background:
    Given a user with access to the Notification Rules page
    And the user is on the Notification Rules page

  Scenario: Empty Notification Rules List
    Then the user see "No result found" in the table
    And the pagination is disabled

  Scenario Outline: Listing Notification Rules with Pagination
    Given the user have <count> Notification Rules
    And the number of results per page is set to <max_per_page>
    And the current page is <current_page>
    Then the user see the total results as <count>
    And the user see the link to the previous page status as <previous_page>
    And the user see the link to the next page status as <next_page>

    Examples:
      | count | max_per_page | current_page | previous_page | next_page |
      | 1     | 10           | 1            | disabled      | disabled  |
      | 15    | 10           | 1            | disabled      | enabled   |
      | 15    | 10           | 2            | enabled       | disabled  |
      | 21    | 10           | 1            | disabled      | enabled   |
      | 21    | 10           | 2            | enabled       | enabled   |
      | 21    | 10           | 3            | enabled       | disabled  |
      | 21    | 50           | 1            | disabled      | disabled  |
