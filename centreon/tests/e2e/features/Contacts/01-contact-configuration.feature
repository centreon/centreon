Feature: ContactConfiguration
  As a Centreon admin user
  I want to create a contact
  To configure it

  Background:
    Given an admin user is logged in a Centreon server
    And a contact is configured

  @TEST_MON-151165
  Scenario: Edit one existing contact
    When the user updates some contact properties
    Then these properties are updated

  @TEST_MON-151166
  Scenario: Duplicate one existing contact
    When the user duplicates the configured contact
    Then a new contact is created with identical properties

  @TEST_MON-151167
  Scenario: Delete one existing contact
    When the user deletes the configured contact
    Then the deleted contact is not visible anymore on the contact page