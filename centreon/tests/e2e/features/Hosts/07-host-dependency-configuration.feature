Feature: Hosts dependency configuration
  As a Centreon admin
  I want to manipulate a host dependency
  To see if all simples manipulations work

  Background:
    Given a user is logged in a Centreon server
    And some hosts and services are configured
    And a host dependency is configured

  @TEST_MON-156456
  Scenario: Change the properties of a host dependency
    When the user changes the properties of a host dependency
    Then the properties are updated

  @TEST_MON-156457
  Scenario: Duplicate one existing host dependency
    When the user duplicates a host dependency
    Then the new host dependency has the same properties

  @TEST_MON-156459
  Scenario: Delete one existing host dependency
    When the user deletes a host dependency
    Then the deleted host dependency is not displayed in the list