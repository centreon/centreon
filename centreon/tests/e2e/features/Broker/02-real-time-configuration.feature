Feature: Real-time database writes of the configuration
  As a Centreon administrator
  I want the Broker to write the hosts, services and host groups loaded by the Engine to the real-time database
  So that Resources Status lists what is monitored and nothing else

  @MON-214363 @system
  Scenario: A reload writes a new host, its host group membership and their removal to the real-time database
    Given an administrator is logged in a Centreon server
    And a host group and a host member of it with one service are created and exported
    When the Engine has reloaded the configuration
    Then the host and its service are enabled resources of the real-time database
    And the host is linked to its host group in the real-time database
    And filtering Resources Status on the host group lists the host Up and its service Ok
    When the host is removed from the host group and the configuration is exported
    Then the real-time database no longer links the host to the group
    When the host is deleted and the configuration is exported
    Then the host and its service are no longer enabled resources
    And Resources Status no longer finds them
