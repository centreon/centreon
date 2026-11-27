Feature: Host created from a host template
  As a Centreon administrator
  I want a host created from a host template to inherit its settings and its services
  So that the monitoring follows the template and its later changes

  @MON-214309 @system
  Scenario: A host created from a host template inherits its settings and its services
    Given an admin user is logged in a Centreon server
    And a host template "rbt-host-template" with a normal check interval of 7 and the linked service template "rbt-ping-template"
    When the user adds the host "rbt-inherit-host" with the template "rbt-host-template" and creates the services linked to the template
    Then the host is listed with the service "rbt-ping" deployed from the template
    When the user exports the configuration of the monitoring server with a reload
    Then the generated hosts.cfg declares "rbt-inherit-host" with "use rbt-host-template"
    And the host is Up and its service "rbt-ping" is Ok in Resources status
    And the real-time database stores a check interval of 7 for the host
    When the user sets the normal check interval of "rbt-host-template" to 9 and exports the configuration again
    Then the real-time database stores a check interval of 9 for the host
    When the user deletes "rbt-inherit-host" and exports the configuration again
    Then "rbt-inherit-host" is no longer found in Resources status
