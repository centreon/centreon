@REQ_MON-212209
Feature: Event Logs pages without counting the results
  As a Centreon user
  I want to move between the pages of the Event Logs list and export it
  So that a search stays fast and the exports match the displayed list

  # The paging does not depend on the user's rights: the same page script and
  # endpoint run for every user, so one persona is enough.

  Background:
    Given an administrator is logged in

  Scenario: The list moves between pages with the "Previous page" and "Next page" buttons
    Given 12 events with the output "E2E_PAGER" exist
    And the administrator opens the Event Logs page
    When the administrator searches the events with the output "E2E_PAGER" and 10 events per page
    Then 10 events are displayed
    And the "Previous page" buttons are disabled
    And the "Next page" buttons are enabled
    When the administrator goes to the next page
    Then the Event Logs search asks for page 2
    And 2 events are displayed
    And the "Previous page" buttons are enabled
    And the "Next page" buttons are disabled
    When the administrator goes to the previous page
    Then the Event Logs search asks for page 1
    And 10 events are displayed

  Scenario: No next page is offered when the events exactly fill the page
    Given 10 events with the output "E2E_PAGER" exist
    And the administrator opens the Event Logs page
    When the administrator searches the events with the output "E2E_PAGER" and 10 events per page
    Then 10 events are displayed
    And the "Next page" buttons are disabled

  Scenario: A new search and a new page size start again from the first page
    Given 12 events with the output "E2E_PAGER" exist
    And the administrator opens the Event Logs page
    And the administrator searched the events with the output "E2E_PAGER" and 10 events per page
    And the administrator went to the next page
    When the administrator clicks the "Run query" button
    Then the Event Logs search asks for page 1
    When the administrator goes to the next page
    And the administrator changes the page size to 20
    Then the Event Logs search asks for page 1 with 20 events per page

  Scenario Outline: The <action> keeps the filters of the displayed list
    Given 12 events with the output "E2E_PAGER" exist
    And the administrator opens the Event Logs page
    And the administrator searched the events with the output "E2E_PAGER" and 10 events per page
    When the administrator changes the output filter to "other" without running the query
    And the administrator uses the <action>
    Then the request of the <action> filters on the output "E2E_PAGER"

    Examples:
      | action      |
      | next page   |
      | CSV export  |
      | XML export  |

  Scenario: The XML export contains all the events of the search, not only the page
    Given 12 events with the output "E2E_PAGER" exist
    And the administrator opens the Event Logs page
    And the administrator searched the events with the output "E2E_PAGER" and 10 events per page
    When the administrator uses the XML export
    Then the XML export contains 12 events

  Scenario: The engine logs list keeps its numbered pages
    Given 12 engine messages with the output "E2E_ENGINE" exist
    When the engine logs list is requested with 10 messages per page
    Then the engine logs list offers numbered pages
