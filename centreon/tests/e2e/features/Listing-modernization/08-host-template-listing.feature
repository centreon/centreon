Feature: Modern host template listing
    As a Centreon admin
    I want to manage host templates from the modernized listing page
    With AJAX search, locked elements support, pagination and bulk actions

  Background:
    Given an admin user is logged in Centreon
    And several host templates exist

  Scenario: Host template listing loads via AJAX
    When the user navigates to the host templates listing
    Then the AJAX listing table is displayed with host template rows

  Scenario: Search filters host templates by name
    When the user navigates to the host templates listing
    And the user searches for a specific host template
    Then only the matching host template is displayed

  Scenario: Locked checkbox shows and hides locked templates
    When the user navigates to the host templates listing
    And the locked checkbox is checked
    Then locked host templates are visible
    When the user unchecks the locked checkbox and searches
    Then locked host templates are hidden

  Scenario: Locked templates have disabled checkboxes and dup inputs
    When the user navigates to the host templates listing
    And the locked checkbox is checked
    Then locked rows have disabled selection checkboxes
    And locked rows have disabled duplication inputs

  Scenario: Pagination and rows per page
    When the user navigates to the host templates listing
    Then the pagination info shows the total count
    When the user changes the rows per page to 10
    Then at most 10 rows are displayed

  Scenario: Bulk duplication works
    When the user navigates to the host templates listing
    And the user selects a host template and duplicates it
    Then a duplicated host template appears in the listing

  Scenario: Bulk deletion works
    When the user navigates to the host templates listing
    And the user selects a host template and deletes it
    Then the host template is removed from the listing

  Scenario: Clicking a name navigates to the edit form
    When the user navigates to the host templates listing
    And the user clicks on a host template name
    Then the host template edit form is displayed

  Scenario: Session state persists across navigation
    When the user navigates to the host templates listing
    And the user searches for a specific host template
    And the user clicks on a host template name
    And the user navigates back to the host templates listing
    Then the search field still contains the search term
