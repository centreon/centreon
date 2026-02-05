Feature: HostConfiguration
  As a Centreon admin
  I want to modify a host
  To see if the modification is saved on the Host Page

  Background:
    Given an admin user is logged in a Centreon server
    And a host is configured

  @TEST_MON-177105
  Scenario: Edit the name of a host
    When the admin changes the name of a host to "Edited Host Name"
    Then the updated name should be updated on the host page to "Edited Host Name"

  @TEST_MON-177106
  Scenario: Duplicate one existing host
    When the admin duplicates a host
    Then a new host is created with identical fields

  @TEST_MON-177107
  Scenario: Delete one existing host
    When the admin deletes the host
    Then the host is not visible in the host list

  @TEST_MON-177011
  Scenario: Add a host with geo-coordinates exceeding 32 characters
    Given the admin is on the hosts listing page
    And the admin fills in the required fields to create a host
    And the admin enters this non valid value "48.85503400000000000000,2.34667000000000000000" in the geo-coordinates field
    When the admin saves the host
    Then the host is successfully created
    And the geo-coordinates value is truncated "48.855034,2.346670"

  @TEST_MON-177012
  Scenario: Edit an existing host and enter geo-coordinates longer than 32 characters
    Given the admin is on the hosts listing page
    And a host is already configured
    When the admin opens the edit form on this host
    And the admin enters this non valid value "48.85503400000000000000,2.34667000000000000000" in the geo-coordinates field
    When the admin saves the host
    Then the host is successfully created
    And the geo-coordinates value is truncated "48.855034,2.346670"