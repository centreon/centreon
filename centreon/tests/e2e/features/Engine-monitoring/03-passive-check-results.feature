Feature: Passive check results
  As a Centreon administrator
  I want Engine to apply the results written to its command file
  So that services fed by an external tool show their real state

  @MON-214350 @system
  Scenario: A passive result written to the Engine command file sets the state and output of the service
    Given an administrator is logged in a Centreon server
    And a host "rbt-passive-host" with a passive service "rbt-passive-svc" exported to the poller
    And the Engine external command file of the poller exists
    When a Critical result whose output contains a backslash is written to the command file for "rbt-passive-svc"
    Then centengine.log reports the external command and the passive service check
    And "rbt-passive-svc" is Critical with the full output and its perfdata
    When an Ok result is written to the command file
    Then "rbt-passive-svc" is Ok with the new output
    When a result is written for a service that does not exist
    Then Engine logs it and keeps running with the same PID
