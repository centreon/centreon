Feature: Poller reporting to the central
  As a Centreon administrator
  I want a poller to report its status and its check results to the central
  So that the resources it monitors are seen from the central

  # The poller is the container of the "poller" compose profile: it registers
  # itself to the central and is named after its container hostname.

  @MON-214301 @system
  Scenario: A running poller reports its status to the central
    Given an administrator is logged in a Centreon server
    And a poller is declared, exported and its Engine and Gorgone are running
    When the user opens the pollers configuration page
    Then the poller is displayed as running with a PID and a version
    And its last update is less than 2 minutes old
    When the user opens the pollers tile of the top banner
    Then the poller is counted as running without latency or stopped Engine
    When the user waits 2 minutes and reloads the pollers configuration page
    Then the last update of the poller has moved forward

  @MON-214302 @system
  Scenario: A host monitored by a poller reports check results to the central
    Given an administrator is logged in a Centreon server
    And a poller is running and reports its status to the central
    When the user creates a host monitored by the poller with one active service
    And the user exports the poller configuration with reload
    Then the export log reports no error
    When the user opens the Resources status page and searches the host
    Then the host and its service are no longer in PENDING status
    And their last check is less than 2 minutes old
    And the monitoring server displayed for the host is the poller
    When the user waits one check interval and refreshes the page
    Then the last check of the host has moved forward
