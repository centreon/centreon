Feature: Modern escalation listing
    As a Centreon admin
    I want to manage escalations from the modernized listing page
    With AJAX search, pagination and bulk actions

  Background:
    Given an admin user is logged in Centreon
    And an escalation exists

  @TEST_LISTING-103
  Scenario: Escalation listing loads via AJAX
    When the user navigates to the escalations listing
    Then the AJAX listing table is displayed with escalation rows

  @TEST_LISTING-104
  Scenario: Search filters escalations by name
    When the user navigates to the escalations listing
    And the user searches for a specific escalation
    Then only the matching escalation is displayed

  @TEST_LISTING-105
  Scenario: Pagination info is displayed
    When the user navigates to the escalations listing
    Then the pagination info shows the total count

  @TEST_LISTING-106
  Scenario: Bulk duplication works
    When the user navigates to the escalations listing
    And the user selects an escalation and duplicates it
    Then a duplicated escalation appears in the listing

  @TEST_LISTING-107
  Scenario: Bulk deletion works
    When the user navigates to the escalations listing
    And the user selects an escalation and deletes it
    Then the escalation is removed from the listing

  @TEST_LISTING-108
  Scenario: Clicking a name navigates to the edit form
    When the user navigates to the escalations listing
    And the user clicks on an escalation name
    Then the escalation edit form is displayed

  @TEST_LISTING-109
  Scenario: Session state persists across navigation
    When the user navigates to the escalations listing
    And the user searches for a specific escalation
    And the user clicks on an escalation name
    And the user navigates back to the escalations listing
    Then the search field still contains the search term
