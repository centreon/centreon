@REQ_MON-151193
Feature: Edit a service template
    As a Centreon user
    I want to manipulate a service
    To see if all simples manipulations work

  Background:
    Given a user is logged in Centreon
    And a service template is configured

  @TEST_MON-151190
  Scenario: Change the properties of a service template
    When the user changes the properties of a service template
    Then the properties are updated

  @TEST_MON-151559
  Scenario: Duplicate one existing service template
    When the user duplicates a service template
    Then the new service template has the same properties

  @TEST_MON-151560
  Scenario: Delete one existing service template
    When the user deletes a service template
    Then the deleted service template is not displayed in the list
