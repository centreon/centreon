Feature: Service dependency configuration
  As a Centreon user
  I want to manipulate a service dependency
  To see if all simple manipulations work

  Background:
    Given a user is logged in a Centreon server
    And some hosts and services and service groups are configured

  @TEST_MON-156576
  Scenario: Change the properties of a service dependency
    Given a service dependency is configured
    When the user changes the properties of a service dependency
    Then the properties are updated

  @TEST_MON-156578
  Scenario: Duplicate one existing service dependency
    Given a service dependency is configured
    When the user duplicates a service dependency
    Then the new service dependency has the same properties

  @TEST_MON-156579
  Scenario: Delete one existing service dependency
    Given a service dependency is configured
    When the user deletes a service dependency
    Then the deleted service dependency is not displayed in the list

  @TEST_MON-156893
  Scenario: Change the properties of a service group dependency
    Given a service group dependency is configured
    When the user changes the properties of a service group dependency
    Then the properties of the service group dependency are updated

  @TEST_MON-156896
  Scenario: Duplicate one existing service group dependency
    Given a service group dependency is configured
    When the user duplicates a service group dependency
    Then the new service group dependency has the same properties

  @TEST_MON-156897
  Scenario: Delete one existing service group dependency
    Given a service group dependency is configured
    When the user deletes a service group dependency
    Then the deleted service group dependency is not displayed in the list