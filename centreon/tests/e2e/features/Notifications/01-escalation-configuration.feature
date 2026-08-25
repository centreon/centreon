Feature: Configuration of an escalation
  As a Centreon user
  I want to manipulate an escalation
  To see if all simple manipulations work

  Background:
    Given an admin user is logged in a Centreon server

  @MON-157181
  Scenario: Create an escalation
    Given some meta services are configured
    And some service groups are configured
    When the user fills all the properties of an escalation
    And the user clicks on save
    Then the escalation is displayed on the listing

  Scenario: The escalations listing loads through the AJAX framework
    When the user opens the escalations listing
    Then the AJAX listing table is displayed with the configured escalation

  Scenario: The listing shows pagination information
    When the user opens the escalations listing
    Then the pagination information shows the total count

  Scenario: The search filters the escalations by name
    When the user opens the escalations listing
    And the user searches for a term matching no escalation
    Then no escalation is displayed
    When the user searches for the configured escalation
    Then only the matching escalation is displayed

  Scenario: The search term persists across navigation
    When the user opens the escalations listing
    And the user searches for the configured escalation
    And the user opens the escalation form and comes back to the listing
    Then the search field still contains the search term

  @MON-157182
  Scenario: Change the properties of one existing escalation
    When the user changes the properties of the configured escalation
    Then the properties are updated

  @MON-157183
  Scenario: Duplicate one existing escalation
    When the user duplicates the configured escalation
    Then a new escalation is created with identical properties

  @MON-157184
  Scenario: Delete one existing escalation
    When the user deletes the configured escalation
    Then the deleted escalation is not displayed in the list of escalations
