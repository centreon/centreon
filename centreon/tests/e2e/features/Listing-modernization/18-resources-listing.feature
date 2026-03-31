Feature: Modern resources ($USERn$ macros) listing
    As a Centreon admin
    I want to manage engine resources from the modernized listing page
    With AJAX search, toggle, pagination and bulk actions

  Background:
    Given an admin user is logged in Centreon

  Scenario: Resources listing loads via AJAX
    When the user navigates to the resources listing
    Then the AJAX listing table is displayed with resource rows
    And the USER1 resource is visible

  Scenario: Search filters resources by name
    When the user navigates to the resources listing
    And the user searches for a specific resource
    Then only the matching resource is displayed

  Scenario: Toggle disables a resource
    When the user navigates to the resources listing
    And the user clicks the toggle to disable a resource
    Then the resource toggle switches to disabled
    And the toggle response is successful

  Scenario: Toggle enables a resource
    When the user navigates to the resources listing
    And the resource is disabled
    And the user clicks the toggle to enable the resource
    Then the resource toggle switches to enabled

  Scenario: Pagination info is displayed
    When the user navigates to the resources listing
    Then the pagination info shows the total count

  Scenario: Bulk duplication works
    When the user navigates to the resources listing
    And the user selects a resource and duplicates it
    Then a duplicated resource appears in the listing

  Scenario: Clicking a name navigates to the edit form
    When the user navigates to the resources listing
    And the user clicks on a resource name
    Then the resource edit form is displayed

  Scenario: Session state persists across navigation
    When the user navigates to the resources listing
    And the user searches for a specific resource
    And the user clicks on a resource name
    And the user navigates back to the resources listing
    Then the search field still contains the search term
