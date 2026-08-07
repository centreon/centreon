Feature: ContactGroupConfiguration
  As a Centreon admin
  I want to manipulate a contact group
  To see if all simples manipulations work

  Background:
    Given an admin user is logged in a Centreon server
    And a contact group is configured

  @MON-151337
  Scenario: Edit the properties of a contact group
    When the user updates the properties of the configured contact group
    Then the properties are updated

  @MON-151338
  Scenario: Duplicate one existing contact group
    When the user duplicates the configured contact group
    Then a new contact group is created with identical properties

  @MON-151339
  Scenario: Delete one existing contact group
    When the user deletes the configured contact group
    Then the deleted contact group is not visible anymore on the contact group page

  @MON-200035
  Scenario: The contact groups listing loads its rows over AJAX
    When the user displays the contact groups listing
    Then the listing table is displayed with contact group rows

  @MON-200035
  Scenario: Searching filters the contact groups listing
    When the user displays the contact groups listing
    And the user searches for the configured contact group
    Then only the matching contact group is displayed

  @MON-200035
  Scenario: Toggling a contact group disables it
    When the user displays the contact groups listing
    And the user clicks the toggle to disable the contact group
    Then the contact group toggle switches to disabled
