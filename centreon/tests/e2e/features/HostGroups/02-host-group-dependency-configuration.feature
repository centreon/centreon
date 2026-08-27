Feature: Host Group Dependency Configuration
  As a Centreon admin
  I want to manipulate a host group dependency
  To see if all simple manipulations work

  Background:
    Given a user is logged in a Centreon server
    And some hosts groups are configured
    And a host group dependency is configured

  Scenario: The host group dependencies listing loads through the AJAX framework
    When the user opens the host group dependencies listing
    Then the AJAX listing table is displayed with the configured host group dependency

  Scenario: The search filters the host group dependencies by name
    When the user opens the host group dependencies listing
    And the user searches for a term matching no host group dependency
    Then no host group dependency is displayed
    When the user searches for the configured host group dependency
    Then only the matching host group dependency is displayed

  Scenario: The listing shows pagination information
    When the user opens the host group dependencies listing
    Then the pagination information shows the total count

  @MON-156507
  Scenario: Change the properties of a host group dependency
    When the user changes the properties of a host group dependency
    Then the properties are updated

  @MON-156508
  Scenario: Duplicate one existing host group dependency
    When the user duplicates a host group dependency
    Then the new object has the same properties

  @MON-156509
  Scenario: Delete one existing host group dependency
    When the user deletes a host group dependency
    Then the deleted object is not displayed in the list
