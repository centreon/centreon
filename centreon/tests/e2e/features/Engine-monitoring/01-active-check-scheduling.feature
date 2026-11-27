Feature: Active check scheduling
  As a Centreon administrator
  I want Engine to check my services at their normal and retry intervals
  So that a problem is confirmed after the configured number of attempts

  @MON-214348 @system
  Scenario: An active service is checked at its normal interval, retried at its retry interval and confirmed in a hard state
    Given an administrator is logged in a Centreon server
    And the test check command "rbt-sched-check" returns the exit code written for each service
    And a host "rbt-sched-host" with an active service "rbt-sched-svc" checked every 2 minutes, retried every minute, with 3 max check attempts
    When the configuration of the poller is exported and reloaded
    Then "rbt-sched-svc" is Ok in a hard state and is checked every 2 minutes
    When the check of "rbt-sched-svc" starts returning Critical
    Then the service is Critical in a soft state at attempt 1 and is retried every minute
    And after its third attempt the service is Critical in a hard state and is checked every 2 minutes again
    When the check returns Ok again
    Then the service is Ok in a hard state after its next check
