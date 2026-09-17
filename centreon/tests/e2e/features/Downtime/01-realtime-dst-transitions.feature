# TODO: add @REQ_MON-<id> once the functional requirement for downtime DST is identified
Feature: Realtime downtime around DST transitions
  As a Centreon user
  I want realtime downtimes to be scheduled correctly across daylight saving time
  So that maintenance windows keep their intended duration when clocks change

  Background:
    Given a user logged in with the Europe/Paris timezone
    And a passive service is monitored

  @MON-205045
  Scenario: realtime downtime over a full spring-forward day lasts 23h
    When a realtime downtime covering the whole spring-forward day is applied
    Then the scheduled downtime matches the expected start, end and duration

  @MON-205044
  Scenario: realtime downtime over a full fall-back day lasts 25h
    When a realtime downtime covering the whole fall-back day is applied
    Then the scheduled downtime matches the expected start, end and duration

  @MON-205043
  Scenario: realtime downtime starting on a non-existent spring-forward time is clamped
    When a realtime downtime starting at the non-existent spring-forward time is applied
    Then the scheduled downtime matches the expected start, end and duration

  @MON-205042
  Scenario: realtime downtime fully inside the spring-forward gap is not scheduled
    When a realtime downtime fully inside the spring-forward gap is applied
    Then the downtime is not scheduled
