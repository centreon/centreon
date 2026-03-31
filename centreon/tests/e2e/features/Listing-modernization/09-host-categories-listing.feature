Feature: Modern host categories listing
    As a Centreon admin
    I want to manage host categories from the modernized listing page
    With AJAX search, toggle, pagination and bulk actions

  Background:
    Given an admin user is logged in Centreon
    And several host categories exist

  @TEST_LISTING-075
  Scenario: Host categories listing loads via AJAX
    When the user navigates to the host categories listing
    Then the AJAX listing table is displayed with host category rows

  @TEST_LISTING-076
  Scenario: Search filters host categories by name
    When the user navigates to the host categories listing
    And the user searches for a specific host category
    Then only the matching host category is displayed

  @TEST_LISTING-077
  Scenario: Toggle disables a host category
    When the user navigates to the host categories listing
    And the user clicks the toggle to disable a host category
    Then the toggle switches to disabled state
    And the toggle response is successful

  @TEST_LISTING-078
  Scenario: Toggle enables a host category
    When the user navigates to the host categories listing
    And the host category is disabled
    And the user clicks the toggle to enable the host category
    Then the toggle switches to enabled state

  @TEST_LISTING-079
  Scenario: Pagination and rows per page
    When the user navigates to the host categories listing
    Then the pagination info shows the total count

  @TEST_LISTING-080
  Scenario: Bulk duplication works
    When the user navigates to the host categories listing
    And the user selects a host category and duplicates it
    Then a duplicated host category appears in the listing

  @TEST_LISTING-081
  Scenario: Bulk deletion works
    When the user navigates to the host categories listing
    And the user selects a host category and deletes it
    Then the host category is removed from the listing

  @TEST_LISTING-082
  Scenario: Clicking a name navigates to the edit form
    When the user navigates to the host categories listing
    And the user clicks on a host category name
    Then the host category edit form is displayed

  @TEST_LISTING-083
  Scenario: Session state persists across navigation
    When the user navigates to the host categories listing
    And the user searches for a specific host category
    And the user clicks on a host category name
    And the user navigates back to the host categories listing
    Then the search field still contains the search term
