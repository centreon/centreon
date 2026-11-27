Feature: Check period
  As a Centreon administrator
  I want Engine to check a service only inside its check period
  So that no check runs when the service is not expected to be monitored

  @MON-214308 @system
  Scenario: A service is not checked outside its check period
    Given an administrator is logged in a Centreon server
    And a time period "rbt-tp-closed" with no time range on any day
    And a host "rbt-tp-host" with two services checked every minute, "rbt-tp-open" on the check period "24x7" and "rbt-tp-closed" on the check period "rbt-tp-closed"
    When the configuration of the Central is exported and reloaded
    Then timeperiods.cfg contains "rbt-tp-closed" without any day line and services.cfg sets the check_period of "rbt-tp-closed" on it
    And within 3 minutes "rbt-tp-open" is Ok with a last check and "rbt-tp-closed" is still Pending without a last check
    When the time period "rbt-tp-closed" is given the range 00:00-24:00 on every day and the configuration is exported again
    Then within 2 minutes "rbt-tp-closed" is checked and leaves the Pending state
