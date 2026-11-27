Feature: Performance data storage
  As a Centreon administrator
  I want the performance data of the check results to be stored in the database and in the RRD files
  So that the metrics of a service can be read and graphed

  @MON-214364 @system
  Scenario: Performance data of a check result is stored in the database and in the RRD files
    Given a passive service is exported and listed in Resources Status
    When check results with performance data are submitted for it
    Then the Broker creates the service index and its metric with the thresholds of the performance data
    And every value is stored in the performance data table at its check time
    And the RRD Broker updates the metric RRD file with the last value at the same time
    And the RRD Broker log has no error for this file
    And the graph of the service shows the submitted values
