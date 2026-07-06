@REQ_MON-151561
Feature: Edit a meta service
  As a Centreon user
  I want to manipulate a meta service
  To see if all simples manipulations work

  Background:
    Given a user is logged in Centreon
    And a meta service is configured

  @MON-151571
  Scenario: Change the properties of a meta service
    When the user changes the properties of a meta service
    Then the properties are updated

  @MON-151572
  Scenario: Duplicate one existing meta service
    When the user duplicates a meta service
    Then the new meta service has the same properties

  @MON-151573
  Scenario: Delete one existing meta service
    When the user deletes a meta service
    Then the deleted meta service is not displayed in the list

  Scenario: Filter the configuration services list by service type
    When the configuration services list is requested for each selection filter
    Then services and meta services are returned according to the selected filter
