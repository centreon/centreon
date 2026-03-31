Feature: Modern pollers listing
    As a Centreon admin
    I want to manage pollers from the modernized listing page
    With AJAX search, toggle, LED status indicators, export button and pagination

  Background:
    Given an admin user is logged in Centreon

  Scenario: Pollers listing loads via AJAX
    When the user navigates to the pollers listing
    Then the AJAX listing table is displayed with poller rows
    And the Central poller is visible

  Scenario: Search filters pollers by name
    When the user navigates to the pollers listing
    And the user searches for a specific poller
    Then only the matching poller is displayed

  Scenario: Toggle disables a poller
    When the user navigates to the pollers listing
    And the user clicks the toggle to disable a poller
    Then the poller toggle switches to disabled
    And the toggle response is successful

  Scenario: Toggle enables a poller
    When the user navigates to the pollers listing
    And the poller is disabled
    And the user clicks the toggle to enable the poller
    Then the poller toggle switches to enabled

  Scenario: LED status indicators are displayed
    When the user navigates to the pollers listing
    Then each poller row has running and configuration status indicators

  Scenario: Tooltips show poller details
    When the user navigates to the pollers listing
    Then poller rows have tooltips with PID, uptime and version info

  Scenario: Pagination and rows per page
    When the user navigates to the pollers listing
    Then the pagination info shows the total count

  Scenario: Clicking a poller name navigates to the edit form
    When the user navigates to the pollers listing
    And the user clicks on the Central poller name
    Then the poller edit form is displayed

  Scenario: Session state persists across navigation
    When the user navigates to the pollers listing
    And the user searches for a specific poller
    And the user clicks on the Central poller name
    And the user navigates back to the pollers listing
    Then the search field still contains the search term
