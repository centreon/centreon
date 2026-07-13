Feature: Modern engine configuration listing
    As a Centreon admin
    I want to manage engine configurations from the modernized listing page
    With AJAX search, toggle, pagination and bulk actions

  Background:
    Given an admin user is logged in Centreon

  Scenario: Engine config listing loads via AJAX
    When the user navigates to the engine config listing
    Then the AJAX listing table is displayed with engine config rows
    And the Central engine config is visible

  Scenario: Search filters engine configs by name
    When the user navigates to the engine config listing
    And the user searches for a specific engine config
    Then only the matching engine config is displayed

  Scenario: Toggle disables an engine config
    When the user navigates to the engine config listing
    And the user clicks the toggle to disable an engine config
    Then the engine config toggle switches to disabled
    And the toggle response is successful

  Scenario: Toggle enables an engine config
    When the user navigates to the engine config listing
    And the engine config is disabled
    And the user clicks the toggle to enable the engine config
    Then the engine config toggle switches to enabled

  Scenario: Pagination info is displayed
    When the user navigates to the engine config listing
    Then the pagination info shows the total count

  Scenario: Bulk duplication works
    When the user navigates to the engine config listing
    And the user selects an engine config and duplicates it
    Then a duplicated engine config appears in the listing

  Scenario: Clicking a name navigates to the edit form
    When the user navigates to the engine config listing
    And the user clicks on an engine config name
    Then the engine config edit form is displayed

  Scenario: Session state persists across navigation
    When the user navigates to the engine config listing
    And the user searches for a specific engine config
    And the user clicks on an engine config name
    And the user navigates back to the engine config listing
    Then the search field still contains the search term
