Feature: HostCategoryConfiguration
  As a Centreon admin
  I want to manipulate a host category
  To see if all simples manipulations work

  Background:
    Given an admin user is logged in a Centreon server
    And a host category is configured

  @TEST_MON-151100
  Scenario: Edit one existing host category
    When the user changes the properties of a host category
    Then the properties are updated

  @TEST_MON-151101
  Scenario: Duplicate one existing host category
    When the user duplicates a host category
    Then a new host category is created with identical properties

  @TEST_MON-151102
  Scenario: Delete one existing host category
    When the user deletes a host category
    Then the deleted host category is not visible anymore on the host category page