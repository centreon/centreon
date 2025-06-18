Feature: Configuration of an escalation
  As a Centreon user
  I want to manipulate an escalation
  To see if all simple manipulations work

  Background:
    Given an admin user is logged in a Centreon server

  @TEST_MON-157181
  Scenario: Create an escalation
    Given some meta services are configured
    And some service groups are configured
    When the user fills all the properties of an escalation
    And the user clicks on save
    Then the escalation is displayed on the listing

  @TEST_MON-157182
  Scenario: Change the properties of one existing escalation
    When the user changes the properties of the configured escalation
    Then the properties are updated

  @TEST_MON-157183
  Scenario: Duplicate one existing escalation
    When the user duplicates the configured escalation
    Then a new escalation is created with identical properties

  @TEST_MON-157184
  Scenario: Delete one existing escalation
    When the user deletes the configured escalation
    Then the deleted escalation is not displayed in the list of escalations