Feature: HostCategoryConfiguration
  As a Centreon admin
  I want to manipulate a host category
  To see if all simples manipulations work

  Background:
    Given an admin user is logged in a Centreon server
    And a host category is configured

  @MON-151100
  Scenario: Edit one existing host category
    When the user changes the properties of a host category
    Then the properties are updated

  @MON-151101
  Scenario: Duplicate one existing host category
    When the user duplicates a host category
    Then a new host category is created with identical properties

  @MON-151102
  Scenario: Delete one existing host category
    When the user deletes a host category
    Then the deleted host category is not visible anymore on the host category page

  Scenario: Add a host category from the legacy form
    When the user adds a host category from the form
    Then the added host category appears in the listing

  Scenario: A severity host category keeps its type, a regular one stays regular
    When the user adds a host category with the severity type enabled
    Then the host category is listed as a severity category
    When the user adds a host category with the severity type disabled
    Then the host category is listed as a regular category

  Scenario: Searching the listing filters it and never breaks the page
    When the user searches the listing for the configured category
    Then only the matching category is listed
    When the user searches the listing with special characters
    Then the listing renders with no result and no error

  Scenario: Bulk disabling then enabling a host category flips its status
    When the user bulk disables the configured host category
    Then the configured host category is disabled
    When the user bulk enables the configured host category
    Then the configured host category is enabled
