Feature: Modern broker configuration listing
    As a Centreon admin
    I want to manage broker configurations from the modernized listing page
    With AJAX search, toggle, pagination and bulk actions

  Background:
    Given an admin user is logged in Centreon

  @TEST_LISTING-146
  Scenario: Broker config listing loads via AJAX
    When the user navigates to the broker config listing
    Then the AJAX listing table is displayed with broker config rows

  @TEST_LISTING-147
  Scenario: Search filters broker configs by name
    When the user navigates to the broker config listing
    And the user searches for a specific broker config
    Then only the matching broker config is displayed

  @TEST_LISTING-148
  Scenario: Toggle disables a broker config
    When the user navigates to the broker config listing
    And the user clicks the toggle to disable a broker config
    Then the broker config toggle switches to disabled
    And the toggle response is successful

  @TEST_LISTING-149
  Scenario: Toggle enables a broker config
    When the user navigates to the broker config listing
    And the broker config is disabled
    And the user clicks the toggle to enable the broker config
    Then the broker config toggle switches to enabled

  @TEST_LISTING-150
  Scenario: Pagination info is displayed
    When the user navigates to the broker config listing
    Then the pagination info shows the total count

  @TEST_LISTING-151
  Scenario: Bulk duplication works
    When the user navigates to the broker config listing
    And the user selects a broker config and duplicates it
    Then a duplicated broker config appears in the listing

  @TEST_LISTING-152
  Scenario: Clicking a name navigates to the edit form
    When the user navigates to the broker config listing
    And the user clicks on a broker config name
    Then the broker config edit form is displayed

  @TEST_LISTING-153
  Scenario: Session state persists across navigation
    When the user navigates to the broker config listing
    And the user searches for a specific broker config
    And the user clicks on a broker config name
    And the user navigates back to the broker config listing
    Then the search field still contains the search term
