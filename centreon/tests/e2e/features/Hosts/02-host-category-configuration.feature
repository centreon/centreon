Feature: HostCategoryConfiguration
  As a Centreon admin
  I want to manage host categories from the modernized listing and form
  So that I can organize my configuration through the AJAX listing, its search,
  pagination, per-row toggle and the side-panel form

  Background:
    Given an admin user is logged in a Centreon server
    And a host category is configured

  Scenario: The host categories listing loads through the AJAX framework
    When the user opens the host categories listing
    Then the AJAX listing table is displayed with the configured host category

  Scenario: The search filters the host categories by name
    Given a second host category is configured
    When the user opens the host categories listing
    And the user searches for the first host category
    Then only the matching host category is displayed

  @MON-151100
  Scenario: Edit one existing host category
    When the user changes the properties of a host category
    Then the properties are updated

  Scenario: Enable and disable a host category from the listing toggle
    When the user opens the host categories listing
    And the user toggles the host category off from the listing
    Then the toggle request succeeds and the category is disabled
    When the user toggles the host category on from the listing
    Then the category is enabled again

  @MON-151101
  Scenario: Duplicate one existing host category
    When the user duplicates a host category
    Then a new host category is created with identical properties

  @MON-151102
  Scenario: Delete one existing host category
    When the user deletes a host category
    Then the deleted host category is not visible anymore on the host category page

  Scenario: The listing shows pagination information
    When the user opens the host categories listing
    Then the pagination information shows the total count

  Scenario: The search term persists across navigation
    When the user opens the host categories listing
    And the user searches for the first host category
    And the user opens the host category form and comes back to the listing
    Then the search field still contains the search term
