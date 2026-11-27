Feature: Real-time data of a poller after its reconnection
  As a Centreon administrator
  I want the real-time data of a poller to stay complete after the poller reconnects to the central
  So that its hosts, host groups and tags are never missing nor stale in the monitoring

  # Uses the "poller" profile of the stack: a poller container registered on the central
  @MON-215120 @system
  Scenario: Hosts, groups and tags are kept after the poller reconnects
    Given an administrator is logged in
    And a poller is monitored with hosts, host groups and tags
    And the poller configuration is exported and monitoring is running
    When the poller is restarted and reconnects to the central
    Then the poller is shown as running
    And the hosts of the poller are listed in the real-time monitoring
    And the host groups of the poller are present in the real-time database
    And the tags of the poller are present in the real-time database
    And no entry of the poller is missing or left over
