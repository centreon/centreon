@REQ_MON-216191
Feature: Event Logs CSV export
  As a Centreon user
  I want the Event Logs CSV export to follow my access rights and the selected resources
  So that the exported file only contains the events I am allowed to see

  Background:
    Given an administrator is logged in
    And two meta-services have generated events

  Scenario: An administrator exports the events of a meta-service
    When the administrator exports the event logs of the first meta-service as CSV
    Then the CSV file only contains the events of the first meta-service

  Scenario: A restricted user exports the events of a granted meta-service
    Given a restricted user is granted access to the Event Logs page and to the first meta-service
    When the restricted user exports the event logs of the first meta-service as CSV
    Then the CSV file only contains the events of the first meta-service

  Scenario: A restricted user exports a meta-service that is not granted
    Given a restricted user is granted access to the Event Logs page and to the first meta-service
    When the restricted user exports the event logs of the second meta-service as CSV
    Then the CSV file contains no event

  Scenario Outline: A meta-service selection that is not a valid identifier is ignored
    When the administrator exports the event logs of the meta-service "<identifier>" as CSV
    Then the CSV file contains no event

    Examples:
      | identifier |
      | abc        |
      | 0          |
      | 4.2        |

  Scenario: A user without access to the Event Logs page cannot export the event logs
    Given a restricted user is granted access to the Resources Status page only
    When the restricted user exports the event logs as CSV
    Then the export is refused

  Scenario: A user without access to the System Logs page cannot export the engine logs
    Given a restricted user is granted access to the Event Logs page and to the first meta-service
    When the restricted user exports the engine logs as CSV
    Then the export is refused
