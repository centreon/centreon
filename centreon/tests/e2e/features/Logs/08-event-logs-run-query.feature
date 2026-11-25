@REQ_MON-212209
Feature: Event Logs search runs only on demand
  As a Centreon user
  I want the Event Logs search to run only when I ask for it
  So that changing the filters does not start expensive searches

  # The search trigger does not depend on the user's rights: the same page script
  # runs for every user, so one persona is enough.

  Background:
    Given an administrator is logged in
    And the administrator opens the Event Logs page

  @MON-216038
  Scenario: Changing the filters does not run the search
    When the administrator changes the Event Logs filters
    Then no Event Logs search is run
    And the "Run query" button is enabled

  @MON-216037
  Scenario: The "Run query" button runs the search and refreshes the list
    When the administrator clicks the "Run query" button
    Then the "Run query" button is disabled while the search runs
    And the Event Logs list is displayed
    And the "Run query" button is enabled again
    When the administrator clicks the "Run query" button
    Then the Event Logs search has run 2 times
    And the Event Logs list is displayed again

  @MON-216036
  Scenario: The "Run query" button is enabled again when the search fails
    Given the Event Logs search fails
    When the administrator clicks the "Run query" button
    Then the "Run query" button is disabled while the search runs
    And the Event Logs search ends with an error
    And the "Run query" button is enabled again
