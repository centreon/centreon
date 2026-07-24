@MON-159125
Feature: Event Logs visibility restricted by ACL
  As a Centreon administrator
  I want the Event Logs page to respect each user's resource access ACL
  So that a restricted user only sees the events of the resources they are allowed to access

  Background:
    Given an administrator is logged in
    And monitored resources have generated events
    And a restricted user is granted access to the Event Logs menu only

  Scenario: A restricted user without resource access sees no events
    When the restricted user opens the Event Logs page
    Then no event is displayed to the restricted user

  Scenario: A restricted user sees only the events of the resources granted by ACL
    Given the restricted user is granted access to specific resources
    When the restricted user opens the Event Logs page
    Then only the events of the granted resources are displayed to the restricted user
