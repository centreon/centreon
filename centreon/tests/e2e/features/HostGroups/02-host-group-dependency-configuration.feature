Feature: Host Group Dependency Configuration
  As a Centreon admin
  I want to manipulate a host group dependency
  To see if all simple manipulations work

  Background:
    Given a user is logged in a Centreon server
    And some hosts groups are configured
    And a host group dependency is configured

  @TEST_MON-156507
  Scenario: Change the properties of a host group dependency
    When the user changes the properties of a host group dependency
    Then the properties are updated

  @TEST_MON-156508
  Scenario: Duplicate one existing host group dependency
    When the user duplicates a host group dependency
    Then the new object has the same properties

  @TEST_MON-156509
  Scenario: Delete one existing host group dependency
    When the user deletes a host group dependency
    Then the deleted object is not displayed in the list