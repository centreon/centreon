Feature: Modern time period listing
    As a Centreon admin
    I want to manage time periods from the modernized listing page
    With AJAX search, pagination and bulk actions

  Background:
    Given an admin user is logged in Centreon
    And several time periods exist

  @TEST_LISTING-096
  Scenario: Time period listing loads via AJAX
    When the user navigates to the time periods listing
    Then the AJAX listing table is displayed with time period rows

  @TEST_LISTING-097
  Scenario: Search filters time periods by name
    When the user navigates to the time periods listing
    And the user searches for a specific time period
    Then only the matching time period is displayed

  @TEST_LISTING-098
  Scenario: Pagination and rows per page
    When the user navigates to the time periods listing
    Then the pagination info shows the total count

  @TEST_LISTING-099
  Scenario: Bulk duplication works
    When the user navigates to the time periods listing
    And the user selects a time period and duplicates it
    Then a duplicated time period appears in the listing

  @TEST_LISTING-100
  Scenario: Bulk deletion works
    When the user navigates to the time periods listing
    And the user selects a time period and deletes it
    Then the time period is removed from the listing

  @TEST_LISTING-101
  Scenario: Clicking a name navigates to the edit form
    When the user navigates to the time periods listing
    And the user clicks on a time period name
    Then the time period edit form is displayed

  @TEST_LISTING-102
  Scenario: Session state persists across navigation
    When the user navigates to the time periods listing
    And the user searches for a specific time period
    And the user clicks on a time period name
    And the user navigates back to the time periods listing
    Then the search field still contains the search term
