Feature: Modern meta service listing
    As a Centreon admin
    I want to manage meta services and their metrics from modernized listing pages
    With AJAX search, toggle, pagination and bulk actions

  Background:
    Given an admin user is logged in Centreon
    And a meta service exists

  Scenario: Meta service listing loads via AJAX
    When the user navigates to the meta services listing
    Then the AJAX listing table is displayed with meta service rows

  Scenario: Search filters meta services by name
    When the user navigates to the meta services listing
    And the user searches for a specific meta service
    Then only the matching meta service is displayed

  Scenario: Toggle disables a meta service
    When the user navigates to the meta services listing
    And the user clicks the toggle to disable a meta service
    Then the meta service toggle switches to disabled
    And the toggle response is successful

  Scenario: Toggle enables a meta service
    When the user navigates to the meta services listing
    And the meta service is disabled
    And the user clicks the toggle to enable the meta service
    Then the meta service toggle switches to enabled

  Scenario: Pagination and rows per page
    When the user navigates to the meta services listing
    Then the pagination info is displayed

  Scenario: Bulk duplication works
    When the user navigates to the meta services listing
    And the user selects a meta service and duplicates it
    Then a duplicated meta service appears in the listing

  Scenario: Bulk deletion works
    When the user navigates to the meta services listing
    And the user selects a meta service and deletes it
    Then the meta service is removed from the listing

  Scenario: Clicking a name navigates to the edit form
    When the user navigates to the meta services listing
    And the user clicks on a meta service name
    Then the meta service edit form is displayed

  Scenario: Session state persists across navigation
    When the user navigates to the meta services listing
    And the user searches for a specific meta service
    And the user clicks on a meta service name
    And the user navigates back to the meta services listing
    Then the search field still contains the search term
