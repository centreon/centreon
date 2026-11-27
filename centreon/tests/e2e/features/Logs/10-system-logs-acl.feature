Feature: System Logs for a non-admin user
  As a non-admin Centreon user
  I want to see and export the engine logs of the pollers my access groups allow
  So that I can follow the pollers I am in charge of without administrator rights

  @MON-215119
  Scenario Outline: A non-admin user sees the engine logs of the allowed pollers in System logs
    Given engine logs exist for several pollers over a given period
    Given a non-admin user with menu access to Event Logs and System Logs and <poller_restriction>
    And the user is logged in
    When the user opens the System Logs page on a period containing engine logs
    Then the engine logs of <visible_pollers> are listed
    When the user selects an allowed poller in the poller tree
    Then only the logs of that poller are listed
    When the user exports the logs to CSV
    Then the export contains the same rows as the screen

    Examples:
      | poller_restriction                      | visible_pollers      |
      | no poller restriction in the ACL        | all pollers          |
      | an ACL restricted to some pollers       | the allowed pollers  |
