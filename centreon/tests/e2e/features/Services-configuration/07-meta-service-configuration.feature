Feature: Meta service configuration
  As a Centreon admin
  I want to manage meta services from the modernized listing and form
  So that I can drive them through the AJAX listing, its search, pagination,
  per-row toggle and the side-panel form

  Background:
    Given a user is logged in Centreon
    And a meta service is configured

  Scenario: The meta service listing loads through the AJAX framework
    When the user opens the meta services listing
    Then the AJAX listing table is displayed with the configured meta service

  Scenario: The search filters the meta services by name
    Given a second meta service exists
    When the user opens the meta services listing
    And the user searches for the first meta service
    Then only the matching meta service is displayed

  @MON-151571
  Scenario: Change the properties of a meta service
    When the user changes the properties of a meta service
    Then the properties are updated

  Scenario: Enable and disable a meta service from the listing toggle
    When the user opens the meta services listing
    And the user toggles the meta service off from the listing
    Then the toggle request succeeds and the meta service is disabled
    When the user toggles the meta service on from the listing
    Then the meta service is enabled again

  @MON-151572
  Scenario: Duplicate one existing meta service
    When the user duplicates a meta service
    Then the new meta service has the same properties

  @MON-151573
  Scenario: Delete one existing meta service
    When the user deletes a meta service
    Then the deleted meta service is not displayed in the list

  Scenario: The listing shows pagination information
    When the user opens the meta services listing
    Then the pagination information shows the total count

  Scenario: The search term persists across navigation
    When the user opens the meta services listing
    And the user searches for the first meta service
    And the user opens the meta service form and comes back to the listing
    Then the search field still contains the search term

  @MON-205080
  Scenario: Filter the configuration services list by service type
    When the configuration services list is requested for each selection filter
    Then services and meta services are returned according to the selected filter
