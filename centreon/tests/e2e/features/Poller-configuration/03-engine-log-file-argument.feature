Feature: Engine log file given as a start argument
  As a Centreon administrator
  I want the Engine to apply the log file options of its configuration whatever way its log path is supplied
  So that the logs of a poller keep the same format when its log path comes from a start argument

  @MON-214862 @system
  Scenario: Start a poller with the default log configuration and a log path argument and see the log file options applied
    Given a poller configured with the default log configuration
    When the poller is started with a log file path given as an argument
    Then the poller starts with no error
    And the log file is written at the given path
    And the log file options of the configuration are applied
