Feature: Edit a trap group
  As a Centreon user
  I want to manipulate a trap group
  To see if all simples manipulations work

  Background:
    Given an admin user is logged in a Centreon server
    And a trap group is configured

  @TEST_MON-151961
  Scenario: Edit one existing trap group
    When the user changes the properties of a trap group
    Then the properties are updated

  @TEST_MON-151963
  Scenario: Duplicate one existing trap group
    When the user duplicates a trap group
    Then the a new trap group is created with identical properties

  @TEST_MON-151964
  Scenario: Delete one existing trap group
    When the user deletes a trap group
    Then the deleted trap group is not visible anymore on the trap group page