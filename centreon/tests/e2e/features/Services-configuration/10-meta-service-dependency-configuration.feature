Feature: Edit a meta service dependency
  As a Centreon user
  I want to manipulate a meta service dependency
  To see if all simples manipulations work

  Background:
    Given an admin user is logged in a Centreon server
    And some meta services are configured
    And a meta service dependency is configured

  Scenario: The meta service dependencies listing loads through the AJAX framework
    When the user opens the meta service dependencies listing
    Then the AJAX listing table is displayed with the configured meta service dependency

  Scenario: The search filters the meta service dependencies by name
    When the user opens the meta service dependencies listing
    And the user searches for a term matching no meta service dependency
    Then no meta service dependency is displayed
    When the user searches for the configured meta service dependency
    Then only the matching meta service dependency is displayed

  Scenario: The listing shows pagination information
    When the user opens the meta service dependencies listing
    Then the pagination information shows the total count

  @MON-156381
  Scenario: Change the properties of one existing meta service dependency
    When the user changes the properties of the configured meta service dependency
    Then the properties are updated

  @MON-156382
  Scenario: Duplicate one existing meta service dependency
    When the user duplicates the configured meta service dependency
    Then a new meta service dependency is created with identical properties

  @MON-156383
  Scenario: Delete one existing meta service dependency
    When the user deletes the configured meta service dependency
    Then the deleted meta service dependency is not displayed in the list of meta service dependencies
