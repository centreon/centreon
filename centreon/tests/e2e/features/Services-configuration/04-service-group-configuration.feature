@REQ_MON-151129
Feature: ServiceGroupConfiguration
  As a Centreon admin
  I want to manipulate a service group
  To see if all simples manipulations work

  Background:
    Given a user is logged in Centreon
    And a service group is configured

  Scenario: Change the properties of a service group
    When the user changes the properties of a service group
    Then the properties of the service group are updated

  Scenario: Duplicate one existing service group
    When the user duplicates a service group
    Then the new service group has the same properties

  Scenario: Delete one existing service group
    When the user deletes a service group
    Then the deleted service group is not displayed in the service group list
