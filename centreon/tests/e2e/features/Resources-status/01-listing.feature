@REQ_MON-22028
Feature: List Resources
  As a user
  I want to list the available Resources and filter them
  So that I can handle associated problems quickly and efficiently

  @TEST_MON-22031
  Scenario: Accessing the page for the first time
    Then the unhandled problems filter is selected
    And only non-ok resources are displayed

  @TEST_MON-22029
  Scenario: Filtering Resources through criterias
    When I put in some criterias
    Then only the Resources matching the selected criterias are displayed in the result

  @TEST_MON-22030
  Scenario: Selecting filters
    Given a saved custom filter
    When I select the custom filter
    Then only Resources matching the selected filter are displayed in the result

  @TEST_MON-156875
  Scenario: Selecting critical services custom filter
    Given a saved critical service filter
    When I select the critical service filter
    Then only the critical services are displayed in the result

  @TEST_MON-156876
  Scenario: Selecting pending hosts custom filter
    Given a saved pending host filter
    When I select the pending host filter
    Then only the pending hosts are displayed in the result

  @TEST_MON-22030
  Scenario: Selecting up hosts custom filter
    Given a saved up host filter
    When I select the up host filter
    Then only the up hosts are displayed in the result

  @TEST_MON-157266
  Scenario: Selecting a host group filter with all service statuses
    Given a saved filter that includes a host group and all possible service statuses
    When I select host group filter with all service statuses
    Then all associated services regardless of their status are shown in the result

  @TEST_MON-157267
  Scenario: Selecting a host group filter with OK and Up statuses
    Given a saved filter that includes a host group and services with OK and Up statuses
    When I select the host group filter with OK and Up statuses
    Then only services with OK and Up statuses are shown in the result

  @TEST_MON-157268
  Scenario: Selecting Up hosts and Critical services filter
    Given a saved filter that includes Up hosts and Critical services
    When I select the Up hosts and Critical services filter
    Then only Critical services associated with Up hosts are shown in the result

  @TEST_MON-157269
  Scenario: Selecting a filter for a host, monitoring server, and OK status
    Given a saved filter that includes a host a monitoring server  and services with OK status
    When I select the filter for the host monitoring server and OK status
    Then only services with OK status associated with the selected host and monitoring server are shown in the result

  @TEST_MON-157270
  Scenario: Selecting a filter for a monitoring server with OK status
    Given a saved filter that includes a monitoring server with OK status
    When I select the filter for the monitoring server with OK status
    Then only services with OK status associated with the selected monitoring server are shown in the result

  @TEST_MON-157272
  Scenario: Selecting a filter for services with OK and Critical statuses
    Given a saved filter that includes services with OK and Critical statuses
    When I select the filter for services with OK and Critical statuses
    Then only services with OK and Critical statuses are shown in the result

  # @TEST_MON-157272
  # Scenario: Selecting a filter for services with OK and Critical statuses
  #   Given a saved filter that includes services with OK and Critical statuses
  #   When I select the filter for services with OK and Critical statuses
  #   Then only services with OK and Critical statuses are shown in the result
