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

  Scenario: Selecting HG, HC, SG and SC filters
    Given resources are monitored
    When the user selects HG_1 in the host group filter
    And the user selects HC_1 in the host category filter
    And the user selects SG_1 in the service group filter
    And the user selects SC_1 in the service category filter
    Then only HOST_A_SVC_1 is displayed

  Scenario: Selecting HG, HC and SG filters
    Given resources are monitored
    When the user selects HG_1 in the host group filter
    And the user selects HC_1 in the host category filter
    And the user selects SG_1 in the service group filter
    Then only HOST_A_SVC_1 and HOST_A_SVC_2 are displayed

  Scenario: Selecting HG, HC and SC filters
    Given resources are monitored
    When the user selects HG_1 in the host group filter
    And the user selects HC_1 in the host category filter
    And the user selects SC_1 in the service category filter
    Then only HOST_A_SVC_1 is displayed

  Scenario: Selecting HG and HC filters
    Given resources are monitored
    When the user selects HG_1 in the host group filter
    And the user selects HC_1 in the host category filter
    Then only HOST_A, HOST_A_SVC_1 and HOST_A_SVC_2 are displayed

  Scenario: Selecting HG, SG and SC filters
    Given resources are monitored
    When the user selects HG_1 in the host group filter
    And the user selects SG_1 in the service group filter
    And the user selects SC_1 in the service category filter
    Then only HOST_A_SVC_1 is displayed

  Scenario: Selecting HG and SG filters
    Given resources are monitored
    When the user selects HG_1 in the host group filter
    And the user selects SG_1 in the service group filter
    Then only HOST_A_SVC_1 and HOST_A_SVC_2 are displayed

  Scenario: Selecting HG and SC filters
    Given resources are monitored
    When the user selects HG_1 in the host group filter
    And the user selects SC_1 in the service category filter
    Then only HOST_A_SVC_1 is displayed

  Scenario: Selecting HG filter
    Given resources are monitored
    When the user selects HG_1 in the host group filter
    Then only HOST_A, HOST_A_SVC_1 and HOST_A_SVC_2 are displayed

  Scenario: Selecting HC, SG and SC filters
    Given resources are monitored
    When the user selects HC_1 in the host category filter
    And the user selects SG_1 in the service group filter
    And the user selects SC_1 in the service category filter
    Then only HOST_A_SVC_1 is displayed

  Scenario: Selecting HC and SG filters
    Given resources are monitored
    When the user selects HC_1 in the host category filter
    And the user selects SG_1 in the service group filter
    Then only HOST_A_SVC_1 and HOST_A_SVC_2 are displayed

  Scenario: Selecting HC and SC filters
    Given resources are monitored
    When the user selects HC_1 in the host category filter
    And the user selects SC_1 in the service category filter
    Then only HOST_A_SVC_1 is displayed

  Scenario: Selecting HC filter
    Given resources are monitored
    When the user selects HC_1 in the host category filter
    Then only HOST_A, HOST_A_SVC_1 and HOST_A_SVC_2 are displayed

  Scenario: Selecting SG and SC filters
    Given resources are monitored
    When the user selects SG_1 in the service group filter
    And the user selects SC_1 in the service category filter
    Then only HOST_A_SVC_1 is displayed

  Scenario: Selecting SG filter
    Given resources are monitored
    When the user selects SG_1 in the service group filter
    Then only HOST_A_SVC_1 and HOST_A_SVC_2 are displayed

  Scenario: Selecting SC filter
    Given resources are monitored
    When the user selects SC_1 in the service category filter
    Then only HOST_A_SVC_1 and HOST_B_SVC_1 are displayed

  Scenario: Selecting no filter
    Given resources are monitored
    When has no filter selected
    Then all resources are displayed