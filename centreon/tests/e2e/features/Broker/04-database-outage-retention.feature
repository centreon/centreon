Feature: Broker retention while the database is down
  As a Centreon administrator
  I want the Broker to keep the events produced while the real-time database is unavailable
  So that no monitoring event is lost when the database comes back

  @MON-214365 @system
  Scenario: Events produced while the database is down are kept by the Broker and written once it is back
    Given a passive service is exported and Ok on a single-server platform
    And the database of the central is stopped
    When twenty state changes with performance data are submitted for the service
    Then the Broker keeps running and holds the events for its database output
    When the database is started again
    Then the database output reconnects and its backlog is emptied
    And the twenty state changes are in the event log table
    And the twenty values are in the performance data table
    And the service shows the last submitted result
    And no retention file is left in the Broker directory
