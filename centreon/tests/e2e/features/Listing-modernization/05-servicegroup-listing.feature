Feature: Modern service group listing
    As a Centreon admin
    I want to manage service groups from the modernized listing page
    With AJAX search, toggle, pagination and bulk actions

  Background:
    Given an admin user is logged in Centreon
    And several service groups exist

  Scenario: Service group listing loads via AJAX
    When the user navigates to the service groups listing
    Then the AJAX listing table is displayed with service group rows

  Scenario: Search filters service groups by name
    When the user navigates to the service groups listing
    And the user searches for a specific service group
    Then only the matching service group is displayed

  Scenario: Toggle disables a service group
    When the user navigates to the service groups listing
    And the user clicks the toggle to disable a service group
    Then the toggle switches to disabled state
    And the AJAX response is successful

  Scenario: Toggle enables a service group
    When the user navigates to the service groups listing
    And the service group is disabled
    And the user clicks the toggle to enable the service group
    Then the toggle switches to enabled state

  Scenario: Two consecutive toggles succeed with CSRF rotation
    When the user navigates to the service groups listing
    And the user toggles a service group off then on
    Then both toggle requests succeed

  Scenario: Pagination works correctly
    When the user navigates to the service groups listing
    Then the pagination info shows the total count
    When the user changes the rows per page to 10
    Then at most 10 rows are displayed

  Scenario: Bulk duplication works
    When the user navigates to the service groups listing
    And the user selects a service group and duplicates it
    Then a duplicated service group appears in the listing

  Scenario: Bulk deletion works
    When the user navigates to the service groups listing
    And the user selects a service group and deletes it
    Then the service group is removed from the listing

  Scenario: Clicking a name navigates to the edit form
    When the user navigates to the service groups listing
    And the user clicks on a service group name
    Then the service group edit form is displayed

  Scenario: Session state persists across navigation
    When the user navigates to the service groups listing
    And the user searches for a specific service group
    And the user clicks on a service group name
    And the user navigates back to the service groups listing
    Then the search field still contains the search term
