Feature: Edit a service category
  As a Centreon user
  I want to manipulate a service
  To see if all simples manipulations work

  Background:
    Given a user is logged in Centreon
    And a service category is configured

  @TEST_MON-162171
  Scenario: Change the properties of a service category
    When the user change the properties of a service category
    Then the properties are updated

  @TEST_MON-162172
  Scenario: Duplicate one existing service category
    When the user duplicate a service category
    Then the new service category has the same properties

  @TEST_MON-162173
  Scenario: Delete one existing service category
    When the user delete a service category
    Then the deleted service category is not displayed in the list
