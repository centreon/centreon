Feature: Engine module retention while the central Broker restarts
  As a Centreon administrator
  I want the Engine Broker module to keep its events while the central Broker is stopped or restarting
  So that no monitoring event is lost or duplicated across a Broker restart

  @MON-214366 @system
  Scenario: Events produced while the central Broker restarts reach the database once the Engine module reconnects
    Given a passive service is exported and Ok on a single-server platform
    When the central Broker is stopped
    Then the Engine keeps running and its Broker module holds the events
    When ten state changes are submitted and the Broker is started again
    Then the module reconnects with the same BBDO version
    And the ten state changes are written to the real-time database
    When ten more state changes are submitted while the Broker restarts
    Then the twenty state changes are written once each
    And the Broker statistics list the Engine module as a connected peer
