Feature: Service group dependency configuration
  As a Centreon user
  I want to manipulate a service group dependency
  To see if all simple manipulations work

  Background:
    Given a user is logged in a Centreon server
    And some hosts and services and service groups are configured
    And a service group dependency is configured

  @TEST_MON-156893
  Scenario: Change the properties of a service group dependency
    When the user changes the properties of a service group dependency
    Then the properties are updated

  @TEST_MON-156896
  Scenario: Duplicate one existing service group dependency
    When the user duplicates a service group dependency
    Then the new service group dependency has the same properties

  @TEST_MON-156897
  Scenario: Delete one existing service group dependency
    When the user deletes a service group dependency
    Then the deleted service group dependency is not displayed in the list