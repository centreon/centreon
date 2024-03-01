@REQ_MON-16473
Feature: Listing Notification Rules
  As a Centreon user with access to the Notification Rules page
  The user wants to view the list of Notification Rules
  So that the user can effectively organize the alerts in the monitoring system

  Background:
    Given a user with access to the Notification Rules page
    And the user is on the Notification Rules page

  @TEST_MON-33219
  Scenario: Listing Notifications Rules without result
    Given no Notification Rules are configured
    When the user goes to Notification Rules Listing
    Then the user sees a message indicating "No result found" in the list
    And the pagination is disabled

  @TEST_MON-33218
  Scenario Outline: Listing Notification Rules with Pagination
    Given the user has <count> Notification Rules
    When the user sets the number of results per page to <max_per_page>
    And the user sets current page to <current_page>
    Then the user sees the total results as <count>
    And the user sees the link to the previous page status as '<previous_page>'
    And the user clicks on the link to navigate to the previous page with status enabled
    And the user sees the link to the next page status as '<next_page>'
    And the user clicks on the link to navigate to the next page with status enabled

    Examples:
      | count | max_per_page | current_page | previous_page | next_page |
      | 1     | 10           | 1            | disabled      | disabled  |
      | 15    | 10           | 1            | disabled      | enabled   |
      | 15    | 10           | 2            | enabled       | enabled   |
      | 21    | 10           | 1            | disabled      | enabled   |
      | 21    | 10           | 2            | enabled       | enabled   |
      | 21    | 10           | 3            | enabled       | enabled   |
      | 21    | 50           | 1            | disabled      | disabled  |
