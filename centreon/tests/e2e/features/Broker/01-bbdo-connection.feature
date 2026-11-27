Feature: BBDO connection between the Engine module and the central Brokers
  As a Centreon administrator
  I want the Engine module and the central Brokers to share one BBDO version and report their connections
  So that the monitoring events reach the real-time database and the RRD files

  @MON-214362 @system
  Scenario: The Engine module and the central Brokers use the same BBDO version and report a connected state in their statistics
    Given an administrator is logged in a Centreon server
    And the central Engine and the two central Broker instances run the installed Broker configuration
    When the user reads the BBDO version of the Broker configurations and of the generated Broker files
    Then the module, the Broker and the RRD Broker use the same BBDO version
    And the logs show the BBDO handshake with that version on both sides
    When the user opens the Broker statistics of the Central
    Then the Broker input lists the Engine module as a connected peer
    And the database and RRD outputs are connected without backlog
    And the RRD Broker input and output are connected
    When the user waits one minute and reloads the statistics
    Then their last update has moved forward
