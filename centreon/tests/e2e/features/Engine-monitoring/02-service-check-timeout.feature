Feature: Service check timeout
  As a Centreon administrator
  I want Engine to stop a check that runs longer than the service check timeout
  So that a hanging plugin does not delay the other checks

  @MON-214349 @system
  Scenario: A check running longer than the service check timeout is stopped and the other checks keep their schedule
    Given an administrator is logged in a Centreon server
    And the Service Check Timeout of the poller's Engine configuration is set to 10 seconds
    And a host "rbt-timeout-host" with two active services checked every minute, "rbt-slow-svc" whose check runs for 30 seconds and "rbt-fast-svc"
    When the configuration of the poller is exported and Engine restarted
    Then within 3 minutes "rbt-slow-svc" is not Ok and its status information reports a timeout
    And its execution time is close to 10 seconds and no check process of "rbt-slow-svc" lives longer than 15 seconds
    And "rbt-fast-svc" is Ok and keeps being checked every minute
    When the check of "rbt-slow-svc" no longer sleeps
    Then "rbt-slow-svc" is Ok after its next check
