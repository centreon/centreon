Feature: HostGroupConfiguration
  As a Centreon admin
  I want to modify a host group
  To see if the modification is saved on the host group page

  Background:
    Given an admin user is logged in a Centreon server
    And a host group is configured

  Scenario: Edit some properties of a host group
    When the user changes some properties of the configured host group
    Then these properties are updated

  Scenario: Duplicate one existing host group
    When the user duplicates the configured host group
    Then a new host group is created with identical properties

  Scenario: Delete one existing host group
    When the user deletes the configured host group
    Then the configured host group is not visible anymore on the host group page