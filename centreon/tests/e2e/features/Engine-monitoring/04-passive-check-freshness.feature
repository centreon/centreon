Feature: Passive check freshness
  As a Centreon administrator
  I want Engine to check a passive service itself when its results get too old
  So that a service whose feed stopped does not keep a stale state

  @MON-214351 @system
  Scenario: A passive service without fresh results is checked actively once its freshness threshold is exceeded
    Given an administrator is logged in a Centreon server
    And the service freshness check is enabled in the poller's Engine configuration with an interval of 10 seconds
    And a host "rbt-fresh-host" with a passive service "rbt-fresh-svc" whose freshness is checked with a threshold of 60 seconds
    And the check command of "rbt-fresh-svc" returns Unknown
    When the configuration of the poller is exported and Engine restarted
    And an Ok result is written to the Engine command file for "rbt-fresh-svc"
    Then "rbt-fresh-svc" is Ok from a passive check
    When no result is received for 3 minutes
    Then centengine.log reports the results of "rbt-fresh-svc" as stale and forces a check
    And "rbt-fresh-svc" is Unknown from an active check
    When an Ok result is written again
    Then "rbt-fresh-svc" is Ok and no new stale warning is logged within its threshold
