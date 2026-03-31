Feature: Modern service template listing
    As a Centreon admin
    I want to manage service templates from the modernized listing page
    With AJAX search, locked elements support, icon display, pagination and bulk actions

  Background:
    Given an admin user is logged in Centreon
    And several service templates exist

  Scenario: Service template listing loads via AJAX
    When the user navigates to the service templates listing
    Then the AJAX listing table is displayed with service template rows

  Scenario: Search filters service templates by name
    When the user navigates to the service templates listing
    And the user searches for a specific service template
    Then only the matching service template is displayed

  Scenario: Each template row has an icon
    When the user navigates to the service templates listing
    Then each row displays a service icon

  Scenario: Template chain is displayed with links
    When the user navigates to the service templates listing
    Then service template rows show the parent template chain as links

  Scenario: Scheduling column shows intervals
    When the user navigates to the service templates listing
    Then service template rows show scheduling intervals

  Scenario: Locked checkbox shows and hides locked templates
    When the user navigates to the service templates listing
    And the locked checkbox is checked
    Then locked service templates are visible
    When the user unchecks the locked checkbox and searches
    Then locked service templates are hidden

  Scenario: Locked templates have disabled checkboxes and dup inputs
    When the user navigates to the service templates listing
    And the locked checkbox is checked
    Then locked rows have disabled selection checkboxes
    And locked rows have disabled duplication inputs

  Scenario: No toggle column exists
    When the user navigates to the service templates listing
    Then no toggle switch is present in the listing

  Scenario: Pagination and rows per page
    When the user navigates to the service templates listing
    Then the pagination info shows the total count
    When the user changes the rows per page to 10
    Then at most 10 rows are displayed

  Scenario: Bulk duplication works
    When the user navigates to the service templates listing
    And the user selects a service template and duplicates it
    Then a duplicated service template appears in the listing

  Scenario: Bulk deletion works
    When the user navigates to the service templates listing
    And the user selects a service template and deletes it
    Then the service template is removed from the listing

  Scenario: Clicking a name navigates to the edit form
    When the user navigates to the service templates listing
    And the user clicks on a service template name
    Then the service template edit form is displayed

  Scenario: Session state persists across navigation
    When the user navigates to the service templates listing
    And the user searches for a specific service template
    And the user clicks on a service template name
    And the user navigates back to the service templates listing
    Then the search field still contains the search term
