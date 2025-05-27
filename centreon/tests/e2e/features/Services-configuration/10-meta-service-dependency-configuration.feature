Feature: Edit a meta service dependency
  As a Centreon user
  I want to manipulate a meta service dependency
  To see if all simples manipulations work

  Background:
    Given an admin user is logged in a Centreon server
    And some meta services are configured
    And a meta service dependency is configured

  @TEST_MON-156381
  Scenario: Change the properties of one existing meta service dependency
    When the user changes the properties of the configured meta service dependency
    Then the properties are updated

  @TEST_MON-156382
  Scenario: Duplicate one existing meta service dependency
    When the user duplicates the configured meta service dependency
    Then a new meta service dependency is created with identical properties

  @TEST_MON-156383
  Scenario: Delete one existing meta service dependency
    When the user deletes the configured meta service dependency
    Then the deleted meta service dependency is not displayed in the list of meta service dependencies