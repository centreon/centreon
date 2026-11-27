Feature: Read-only access to the host groups
  As a Centreon administrator
  I want a user with a read-only access to the host groups to be unable to create one
  So that the access group rights are enforced on the configuration page

  @MON-215169
  Scenario: A read-only user cannot add a host group from the configuration page
    Given I am logged in a Centreon server
    And an ACL access group gives read-only access to host groups
    Given a user belonging to the read-only access group is logged in
    And the user is on the host groups configuration page
    When the user tries to add a host group
    Then the host group creation is not possible
    And no new host group appears in the host group list
