Feature: Modern service categories listing
    As a Centreon admin
    I want to manage service categories from the modernized listing page
    With AJAX search, toggle, pagination and bulk actions

  Background:
    Given an admin user is logged in Centreon
    And several service categories exist

  Scenario: Service categories listing loads via AJAX
    When the user navigates to the service categories listing
    Then the AJAX listing table is displayed with service category rows

  Scenario: Search filters service categories by name
    When the user navigates to the service categories listing
    And the user searches for a specific service category
    Then only the matching service category is displayed

  Scenario: Toggle disables a service category
    When the user navigates to the service categories listing
    And the user clicks the toggle to disable a service category
    Then the toggle switches to disabled state
    And the AJAX toggle response is successful

  Scenario: Toggle enables a service category
    When the user navigates to the service categories listing
    And the service category is disabled
    And the user clicks the toggle to enable the service category
    Then the toggle switches to enabled state

  Scenario: Pagination and rows per page
    When the user navigates to the service categories listing
    Then the pagination info shows the total count
    When the user changes the rows per page to 10
    Then at most 10 rows are displayed

  Scenario: Bulk duplication works
    When the user navigates to the service categories listing
    And the user selects a service category and duplicates it
    Then a duplicated service category appears in the listing

  Scenario: Bulk deletion works
    When the user navigates to the service categories listing
    And the user selects a service category and deletes it
    Then the service category is removed from the listing

  Scenario: Clicking a name navigates to the edit form
    When the user navigates to the service categories listing
    And the user clicks on a service category name
    Then the service category edit form is displayed

  Scenario: Session state persists across navigation
    When the user navigates to the service categories listing
    And the user searches for a specific service category
    And the user clicks on a service category name
    And the user navigates back to the service categories listing
    Then the search field still contains the search term
