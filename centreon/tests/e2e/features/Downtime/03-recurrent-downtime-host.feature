# TODO: add @REQ_MON-<id> once the functional requirement for recurrent downtimes is identified
@system
Feature: Recurrent downtime on a host
  As a Centreon user
  I want a recurrent downtime targeting a host to be scheduled
  So that the host enters downtime as expected

  Background:
    Given a user logged in with the Europe/Paris timezone

  Scenario: a recurrent downtime on a host is scheduled
    When a recurrent downtime on a host is applied
    Then a host downtime is scheduled
