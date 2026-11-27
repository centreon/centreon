Feature: [Web] Non-admin users cannot remove hosts from a host group under certain conditions even with the right ACLs
  As a non-admin user with write access to the hosts and host groups
  I want to add and remove any host I can reach in a host group
  So that the host group members match what I saved, without duplicates

  @MON-212525
  Scenario: A non-admin user removes a host beyond the first ten reachable hosts from a host group
    Given a non-admin user whose ACL grants write access to hosts and host groups on all resources
    And more than 10 hosts reachable by this user
    And a host group "HG-TEST" containing hosts within and beyond the 10 lowest host ids reachable by the user
    When the user removes from "HG-TEST" a host that is not among the 10 lowest host ids reachable and saves
    Then the host is no longer a member of "HG-TEST"
    And an entry is logged for this change in Administration > Logs

  @MON-212525
  Scenario: A non-admin user removes a host among the first ten reachable hosts from a host group
    Given a non-admin user whose ACL grants write access to hosts and host groups on all resources
    And a host group "HG-TEST" containing hosts within and beyond the 10 lowest host ids reachable by the user
    When the user removes from "HG-TEST" a host among the 10 lowest host ids reachable and saves
    Then the host is no longer a member of "HG-TEST"

  @MON-212525
  Scenario: An admin user removes a host from a host group with more than 10 hosts
    Given a host group containing more than 10 hosts
    When an admin user removes a host from this host group and saves
    Then the host is no longer a member of the host group

  @MON-212525
  Scenario: A non-admin user removes a host group from the Relations tab of a host
    Given a non-admin user whose ACL grants write access to hosts and host groups on all resources
    And a host beyond the 10 lowest host ids reachable by the user, member of "HG-TEST"
    When the user removes the "HG-TEST" relation from the Relations tab of this host and saves
    Then the host is no longer a member of "HG-TEST"

  @MON-212525
  Scenario: A non-admin user adds a host beyond the first ten reachable hosts to a host group
    Given a non-admin user whose ACL grants write access to hosts and host groups on all resources
    When the user adds to "HG-TEST" a host beyond the 10 lowest host ids reachable and saves
    Then the host is a member of "HG-TEST"

  @MON-212525
  Scenario: No duplicate members after a removal
    Given a host was removed from "HG-TEST" by the non-admin user
    When "HG-TEST" is reopened
    Then no host appears twice in the members list
