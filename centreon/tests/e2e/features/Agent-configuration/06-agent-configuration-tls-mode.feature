@REQ_MON-146653
Feature: Create and update a new Agent Configuration without TLS
  As a Centreon user
  I want to visit the Agents Configuration page
  To manage the Agent Configurations without TLS

  @TEST_MON-167666
  Scenario: Create a CMA agent without TLS
    Given a non-admin user is on the Agents Configuration page
    When the user clicks on the "Add poller/agent configuration" button
    Then a pop-up form is displayed
    When the user selects "CMA" as the agent type
    And the user selects "No TLS" as the encryption level
    Then a warning message explaining the No TLS mode for "CMA" is displayed
    And no certificate fields are shown
    When the user enables connection initiated by the poller
    Then no certificate fields are displayed in the Host Configuration section
    When the user fills in the mandatory fields
    And the user clicks "Save"
    Then the first created agent appears on the Agents Configuration page

  @TEST_MON-167667
  Scenario: Create a Telegraf agent without TLS
    Given a non-admin user is on the Agents Configuration page
    When the user clicks on the "Add" button
    Then a pop-up form is displayed
    When the user selects "Telegraf" as the agent type
    And the user selects "No TLS" as the encryption level
    Then a warning message explaining the No TLS mode for "Telegraf" is displayed
    And no Telegraf certificate fields are shown
    When the user fills in the mandatory Telegraf fields
    And the user clicks "Save"
    Then the second agent appears on the Agents Configuration page

  @TEST_MON-167668
  Scenario: Update a CMA agent without TLS
    Given a non-admin user is on the Agents Configuration page
    When the user clicks on the first configured CMA agent
    Then a pop-up with the agent details is displayed
    And a warning message explaining the No TLS mode for "CMA" is displayed
    When the user updates the CMA details
    And the user clicks "Save"
    Then the first configured CMA agent is updated

  @TEST_MON-167669
  Scenario: Update a Telegraf agent without TLS
    Given a non-admin user is on the Agents Configuration page
    When the user clicks on the second configured Telegraf agent
    Then a pop-up with the Telegraf agent details is displayed
    And a warning message explaining the No TLS mode for "Telegraf" is displayed
    When the user updates the Telegraf agent details
    And the user clicks "Save"
    Then the second configured Telegraf agent is updated