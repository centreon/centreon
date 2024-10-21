Feature: ContactGroupConfiguration
    As a Centreon admin
    I want to manipulate a contact group
    To see if all simples manipulations work

    Background:
      Given an admin user is logged in a Centreon server
      And a contact group is configured

    @TEST_MON-151337
    Scenario: Edit the properties of a contact group
      When the user updates the properties of the configured contact group
      Then the properties are updated

    @TEST_MON-151338
    Scenario: Duplicate one existing contact group
      When the user duplicates the configured contact group
      Then a new contact group is created with identical properties

    @TEST_MON-151339
    Scenario: Delete one existing contact group
      When the user deletes the configured contact group
      Then the deleted contact group is not visible anymore on the contact group page