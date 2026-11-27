Feature: State retention
  As a Centreon administrator
  I want Engine to keep the state of my services across a restart
  So that acknowledgements, downtimes and comments are not lost

  @MON-214352 @system
  Scenario: The state, acknowledgement, downtime and comment of a service survive an Engine restart
    Given an administrator is logged in a Centreon server
    And state retention is enabled in the poller's Engine configuration
    And a host "rbt-ret-host" with a passive service "rbt-ret-svc" exported to the poller
    And "rbt-ret-svc" is Critical, acknowledged, in a fixed downtime of one hour and has a persistent comment
    When Engine is restarted on the poller
    Then the retention file holds the state of "rbt-ret-svc", its acknowledgement, downtime and comment
    And after the restart "rbt-ret-svc" is still Critical with the same output and last check
    And it is still acknowledged, in downtime and commented
