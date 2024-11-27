Feature: HostConfiguration
  As a Centreon admin
  I want to modify a host
  To see if the modification is saved on the Host Page

  Background:
    Given an admin user is logged in a Centreon server
    And a host is configured

  Scenario: Edit the name of a host
    When the user changes the name of a host to "New Host Name"
    Then the host name is updated to "New Host Name" on the Host Page

  Scenario: Duplicate one existing host
    When the user duplicates a host
    Then a new host is created with identical fields

  Scenario: Delete one existing host
    When the user deletes the host
    Then the host is not visible in the host list