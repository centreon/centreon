Feature: Modern host listing
    As a Centreon admin
    I want to manage hosts from the modernized listing page
    With AJAX search, select2 filters, toggle, monitoring status and pagination

  Background:
    Given an admin user is logged in Centreon
    And several hosts exist with different properties

  @TEST_LISTING-022
  Scenario: Host listing loads via AJAX
    When the user navigates to the host listing
    Then the AJAX listing table is displayed
    And host rows contain name, alias, address and poller columns

  @TEST_LISTING-023
  Scenario: Search by host name filters results
    When the user navigates to the host listing
    And the user searches for a specific host name
    Then only the matching host is displayed
    And non-matching hosts are hidden

  @TEST_LISTING-024
  Scenario: Search by IP address filters results
    When the user navigates to the host listing
    And the user searches by IP address
    Then only hosts with that address are displayed

  @TEST_LISTING-025
  Scenario: Hostgroup select2 filter works
    When the user navigates to the host listing
    And the user selects a hostgroup in the filter
    And the user clicks the search button
    Then only hosts belonging to that hostgroup are displayed

  @TEST_LISTING-026
  Scenario: Clear button resets a select2 filter
    When the user navigates to the host listing
    And the user selects a hostgroup in the filter
    And the user clicks the search button
    And the user clicks the clear button next to the hostgroup filter
    And the user clicks the search button
    Then all hosts are displayed again

  @TEST_LISTING-027
  Scenario: Toggle disables a host
    When the user navigates to the host listing
    And the test host toggle is enabled
    And the user clicks the toggle to disable the test host
    Then the test host toggle switches to disabled

  @TEST_LISTING-028
  Scenario: Toggle enables a host
    When the user navigates to the host listing
    And the test host is disabled in the database
    And the user clicks the toggle to enable the test host
    Then the test host toggle switches to enabled

  @TEST_LISTING-029
  Scenario: Host icon is displayed with fallback
    When the user navigates to the host listing
    Then each host row has an icon next to the name
    And the icon is either a custom icon or the default host.svg

  @TEST_LISTING-030
  Scenario: Monitoring status badge is displayed
    When the user navigates to the host listing
    Then host rows have a monitoring status badge column
    And the badge has a tooltip with status details

  @TEST_LISTING-031
  Scenario: Template chain links are displayed
    When the user navigates to the host listing
    Then host rows with templates show clickable template links

  @TEST_LISTING-032
  Scenario: Pagination works correctly
    When the user navigates to the host listing
    And the user changes the rows per page to 10
    Then at most 10 host rows are displayed
    When the user navigates to page 2
    Then a different set of hosts is displayed
    And the page indicator shows page 2

  @TEST_LISTING-033
  Scenario: Select all checkbox toggles all row checkboxes
    When the user navigates to the host listing
    And the user clicks the select all checkbox in the header
    Then all host row checkboxes are checked
    When the user clicks the select all checkbox again
    Then all host row checkboxes are unchecked

  @TEST_LISTING-034
  Scenario: Clicking a host name navigates to the edit form
    When the user navigates to the host listing
    And the user clicks on the test host name
    Then the host edit form is displayed with the correct host

  @TEST_LISTING-035
  Scenario: Services link in options column
    When the user navigates to the host listing
    Then each host row has a services link icon in the options column

  @TEST_LISTING-036
  Scenario: Auto-refresh updates data silently
    When the user navigates to the host listing
    Then the listing auto-refreshes after 15 seconds without page reload

  @TEST_LISTING-037
  Scenario: Session state persists filters across navigation
    When the user navigates to the host listing
    And the user searches for a specific host name
    And the user clicks on the test host name
    And the user navigates back to the host listing
    Then the search field still contains the host name
    And the listing shows the same filtered results
