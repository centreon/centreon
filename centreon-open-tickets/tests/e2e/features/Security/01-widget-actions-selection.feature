@REQ_MON-212292
Feature: Open Tickets widget actions on a selection of resources
  As a Centreon user working with the Open Tickets widget,
  I need the acknowledge, schedule check and close ticket actions to only act on the resources I selected and am allowed to see,
  To be sure that a crafted selection can neither inject SQL nor reach the resources of another access group.

  @MON-214170
  Scenario: Open Tickets selection parameter is safe from SQL injection on ack, check and close actions
    Given two services on two different hosts are selected in the Open Tickets widget
    When the user acknowledges the selection
    Then each host is acknowledged once, with one host and one service acknowledgement per host
    When the user schedules a check then a forced check on the selection
    Then the check commands are sent with the right host and service names
    When the user closes the tickets of services with an open ticket
    Then the confirmation table is displayed
    When the service-ack, schedule-check and close-ticket actions are called with a selection containing SQL injection attempts
    Then each call returns the same benign result as a plain selection
    And no SQL error is logged
    Given a non-admin user restricted to one host
    When the user selects that host and another one
    Then only the commands for the allowed host reach the engine
    When a selection without service id is sent
    Then no resource matches and no PHP warning is logged
    When the user opens an existing GLPI rule
    Then the rule form is displayed
