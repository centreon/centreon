Feature: Engine reload with environment macros
  As a Centreon administrator
  I want Engine to reload safely when environment macros are enabled
  So that removing a service that was notified does not stop the monitoring

  @MON-215222 @system
  Scenario: Reload the Engine after removing notified services with environment macros enabled and see host checks keep running
    Given an administrator is logged in
    And environment macros are enabled on the poller's Engine
    And a host is monitored with a service that has notifications enabled
    Given the service is in a non-OK state and its notification has been sent
    When the administrator removes the notified service from the configuration
    And the administrator exports the configuration and reloads the Engine
    And the next active host check is executed
    Then the host check result is shown in the monitoring
    And the Engine is still running
    And the Engine logs show no fatal error
