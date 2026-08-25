Feature: Hosts dependency configuration
  As a Centreon admin
  I want to manipulate a host dependency
  To see if all simples manipulations work

  Background:
    Given a user is logged in a Centreon server
    And some hosts and services are configured
    And a host dependency is configured

  Scenario: The host dependencies listing loads through the AJAX framework
    When the user opens the host dependencies listing
    Then the AJAX listing table is displayed with the configured host dependency

  Scenario: The search filters the host dependencies by name
    When the user opens the host dependencies listing
    And the user searches for a term matching no host dependency
    Then no host dependency is displayed
    When the user searches for the configured host dependency
    Then only the matching host dependency is displayed

  Scenario: The listing shows pagination information
    When the user opens the host dependencies listing
    Then the pagination information shows the total count

  @MON-156456
  Scenario: Change the properties of a host dependency
    When the user changes the properties of a host dependency
    Then the properties are updated

  @MON-156457
  Scenario: Duplicate one existing host dependency
    When the user duplicates a host dependency
    Then the new host dependency has the same properties

  @MON-156459
  Scenario: Delete one existing host dependency
    When the user deletes a host dependency
    Then the deleted host dependency is not displayed in the list
