Feature: Edit a service
  As a Centreon user
  I want to manipulate a service
  To see if all simples manipulations work

  Background:
    Given a user is logged in Centreon
    And a service is configured

  Scenario: Change the properties of a service
    When the user changes the properties of a service
    Then the properties are updated

  Scenario: Duplicate one existing service
    When the user duplicates a service
    Then the new service has the same properties

  Scenario: Delete one existing service
    When the user deletes a service
    Then the deleted service is not displayed in the service list
