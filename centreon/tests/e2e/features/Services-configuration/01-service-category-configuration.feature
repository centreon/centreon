Feature: Service category configuration
  As a Centreon admin
  I want to manage service categories from the modernized listing and form
  So that I can organize my configuration through the AJAX listing, its search,
  pagination, per-row toggle and the side-panel form

  Background:
    Given an admin user is logged in a Centreon server
    And a service category is configured

  Scenario: The service categories listing loads through the AJAX framework
    When the user opens the service categories listing
    Then the AJAX listing table is displayed with the configured service category

  Scenario: The search filters the service categories by name
    Given a second service category is configured
    When the user opens the service categories listing
    And the user searches for the first service category
    Then only the matching service category is displayed

  @MON-162171
  Scenario: Change the properties of a service category
    When the user changes the properties of a service category
    Then the properties are updated

  Scenario: Enable and disable a service category from the listing toggle
    When the user opens the service categories listing
    And the user toggles the service category off from the listing
    Then the toggle request succeeds and the category is disabled
    When the user toggles the service category on from the listing
    Then the category is enabled again

  @MON-162172
  Scenario: Duplicate one existing service category
    When the user duplicates a service category
    Then a new service category is created with identical properties

  @MON-162173
  Scenario: Delete one existing service category
    When the user deletes a service category
    Then the deleted service category is not visible anymore on the service categories page

  Scenario: The listing shows pagination information
    When the user opens the service categories listing
    Then the pagination information shows the total count

  Scenario: The search term persists across navigation
    When the user opens the service categories listing
    And the user searches for the first service category
    And the user opens the service category form and comes back to the listing
    Then the search field still contains the search term
