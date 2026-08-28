Feature: ContactTemplateConfiguration
  As a Centreon admin
  I want to manipulate a contact template
  To see if all simples manipulations work

  Background:
    Given an admin user is logged in a Centreon server
    And a contact template is configured

  @MON-151405
  Scenario: Edit the properties of a contact template
    When the user updates the properties of the configured contact template
    Then the properties are updated

  @MON-151406
  Scenario: Duplicate one existing contact template
    When the user duplicates the configured contact template
    Then a new contact template is created with identical properties

  @MON-151407
  Scenario: Delete one existing contact template
    When the user deletes the configured contact template
    Then the deleted contact template is not visible anymore on the contact template page

  @MON-200035
  Scenario: The contact templates listing loads its rows over AJAX
    When the user displays the contact templates listing
    Then the listing table is displayed with contact template rows

  @MON-200035
  Scenario: Searching filters the contact templates listing
    When the user displays the contact templates listing
    And the user searches for the configured contact template
    Then only the matching contact template is displayed

  @MON-200035
  Scenario: Toggling a contact template disables it
    When the user displays the contact templates listing
    And the user clicks the toggle to disable the contact template
    Then the contact template toggle switches to disabled
