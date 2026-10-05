Feature: Modern AJAX listing common features
    As a Centreon admin
    I want the modernized listing pages to work correctly
    So that I can manage configuration objects efficiently

  Background:
    Given a user is logged in Centreon
    And some service templates exist

  Scenario: AJAX listing loads data without page reload
    When the user navigates to the service templates listing
    Then the listing table is rendered via AJAX
    And the table contains service template rows

  Scenario: Search filters listing results
    When the user navigates to the service templates listing
    And the user types a search term in the search field
    And the user clicks the search button
    Then only matching service templates are displayed
    And the pagination reflects the filtered count

  Scenario: Pagination navigates between pages
    When the user navigates to the service templates listing
    Then the pagination shows the total number of items
    When the user clicks page 2
    Then the listing shows the second page of results
    And the current page indicator shows page 2

  Scenario: Rows per page selector changes limit
    When the user navigates to the service templates listing
    And the user changes the rows per page to 10
    Then the listing shows at most 10 rows
    And the pagination is recalculated

  Scenario: Locked elements checkbox toggles locked templates visibility
    When the user navigates to the service templates listing
    And the locked checkbox is checked
    Then locked service templates are visible in the listing
    When the user unchecks the locked checkbox and searches
    Then locked service templates are hidden from the listing

  Scenario: Locked templates have disabled checkboxes and dup inputs
    When the user navigates to the service templates listing
    And the locked checkbox is checked
    Then locked rows have disabled selection checkboxes
    And locked rows have disabled duplication inputs

  Scenario: Bulk duplication works via the More actions dropdown
    When the user navigates to the service templates listing
    And the user selects a service template checkbox
    And the user selects Duplicate from the More actions dropdown
    Then a duplicated service template appears in the listing

  Scenario: Bulk deletion works via the More actions dropdown
    When the user navigates to the service templates listing
    And the user selects a service template checkbox
    And the user selects Delete from the More actions dropdown
    Then the service template is removed from the listing

  Scenario: Clicking a service template name navigates to the edit form
    When the user navigates to the service templates listing
    And the user clicks on a service template name
    Then the service template edit form is displayed

  Scenario: Session state persists search and pagination across navigation
    When the user navigates to the service templates listing
    And the user types a search term in the search field
    And the user clicks the search button
    And the user clicks on a service template name
    And the user navigates back to the service templates listing
    Then the search field still contains the search term
    And the listing shows the same filtered results
