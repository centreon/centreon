Feature: ContactTemplateConfiguration
  As a Centreon admin
  I want to manipulate a contact template
  To see if all simples manipulations work

  Background:
    Given an admin user is logged in a Centreon server
    And a contact template is configured

  @TEST_MON-151405
  Scenario: Edit the properties of a contact template
    When the user updates the properties of the configured contact template
    Then the properties are updated

  @TEST_MON-151406
  Scenario: Duplicate one existing contact template
    When the user duplicates the configured contact template
    Then a new contact template is created with identical properties

  @TEST_MON-151407
  Scenario: Delete one existing contact template
    When the user deletes the configured contact template
    Then the deleted contact template is not visible anymore on the contact template page