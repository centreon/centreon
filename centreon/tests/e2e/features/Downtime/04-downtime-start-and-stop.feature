# TODO: add @REQ_MON-<id> once the functional requirement for downtime lifecycle is identified
@system
Feature: Service downtime start and stop
  As a Centreon user
  I want fixed and flexible service downtimes to start and stop as scheduled
  So that monitoring is suppressed exactly during the intended window

  Background:
    Given a passive service is monitored

  Scenario: a fixed downtime becomes active during its time window
    Given a fixed downtime is scheduled on the service for the next minutes
    When the downtime start time is reached
    Then the service downtime is active

  Scenario: a fixed downtime ends when its time window is over
    Given a fixed downtime is scheduled on the service for the next minutes
    And the service downtime is active
    When the downtime end time is reached
    Then the service downtime is over

  # @ignore: a flexible downtime only triggers on a HARD non-OK state, which
  # requires the service to be passive (no active check overriding the submitted
  # CRITICAL) with max_check_attempts=1. Reconfiguring the service that way needs
  # an APPLYCFG, whose engine reload breaks the downtime scheduling that follows.
  # The fixed lifecycle (above) is covered; the flexible variant is parked until
  # a non-disruptive way to force a HARD state is found (follow-up ticket).
  @ignore
  Scenario: a flexible downtime becomes active when the service goes critical
    Given a flexible downtime is scheduled on the service for the next minutes
    When the service becomes critical within the downtime window
    Then the service downtime is active

  @ignore
  Scenario: a flexible downtime ends after its duration has elapsed
    Given a flexible downtime is scheduled on the service for the next minutes
    And the service becomes critical within the downtime window
    And the service downtime is active
    When the downtime duration has elapsed
    Then the service downtime is over
