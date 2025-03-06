Feature: Service dependency configuration
  As a Centreon user
  I want to manipulate a service dependency
  To see if all simple manipulations work

  Background:
    Given a user is logged in a Centreon server
    And some hosts and services are configured
    And a service dependency is configured

  @TEST_MON-156576
  Scenario: Change the properties of a service dependency
    When the user changes the properties of a service dependency
    Then the properties are updated

  @TEST_MON-156578
  Scenario: Duplicate one existing service dependency
    When the user duplicates a service dependency
    Then the new service dependency has the same properties

  @TEST_MON-156579
  Scenario: Delete one existing service dependency
    When the user deletes a service dependency
    Then the deleted service dependency is not displayed in the list