Feature: Edit a trap group
  As a Centreon user
  I want to manipulate a trap group
  To see if all simples manipulations work

  Background:
    Given an admin user is logged in a Centreon server
    And a trap group is configured

  @MON-200041
  Scenario: The trap groups listing loads through the AJAX framework
    When the user opens the trap groups listing
    Then the AJAX listing table is displayed with the configured trap group

  @MON-200041
  Scenario: The search filters the trap groups by name
    Given a second trap group is configured
    When the user opens the trap groups listing
    And the user searches for the first trap group
    Then only the matching trap group is displayed

  @MON-151961
  Scenario: Edit one existing trap group
    When the user changes the properties of a trap group
    Then the properties are updated

  @MON-151963
  Scenario: Duplicate one existing trap group
    When the user duplicates a trap group
    Then the a new trap group is created with identical properties

  @MON-151964
  Scenario: Delete one existing trap group
    When the user deletes a trap group
    Then the deleted trap group is not visible anymore on the trap group page
