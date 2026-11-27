Feature: Engine and Broker settings of a poller in the exported files
  As a Centreon administrator
  I want the exported configuration files to hold the Engine and Broker settings of the poller
  So that a setting changed in the interface is the one the poller runs with

  @MON-214300 @system
  Scenario: The exported configuration files reflect the poller's Engine and Broker settings
    Given an administrator is logged in a Centreon server
    And a poller is declared, exported and running
    When the user sets the service check timeout of the poller's Engine configuration to 120
    And the user sets the "core" log level of the poller's Broker module to "debug"
    # The "Conf Changed" flag only follows hosts, services and their groups (listServers.php),
    # so the Engine and Broker changes do not raise it
    Then the pollers list states that only hosts, services and their groups raise the "Conf changed" flag
    When the user exports the poller configuration with the generate, debug, move and restart steps
    Then the export log reports no error
    And the poller's centengine.cfg contains the service check timeout 120
    And the poller's Broker module file contains the "core" log level "debug"
    And the poller is displayed as running with a new PID
